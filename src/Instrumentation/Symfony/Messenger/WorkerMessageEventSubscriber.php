<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Attribute\Traceable;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\InstrumentationTypeEnum;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\InstrumentationTypeInterface;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\TraceStampPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Creates consumer-side spans for messages processed by the Symfony Messenger worker,
 * with support for both auto and attribute-based instrumentation modes,
 * trace context propagation from the producer, and retry/failure metadata.
 */
final class WorkerMessageEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface, InstrumentationTypeInterface
{
    private InstrumentationTypeEnum $instrumentationType = InstrumentationTypeEnum::Auto;

    public function __construct(
        private readonly MultiTextMapPropagator $propagator,
        private readonly TracerInterface $tracer,
        /** @var ServiceLocator<TracerInterface> */
        private readonly ServiceLocator $tracerLocator,
        private readonly TraceStampPropagator $traceStampPropagator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function setInstrumentationType(InstrumentationTypeEnum $type): void
    {
        $this->instrumentationType = $type;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => [
                ['startSpan', 10000],
            ],
            WorkerMessageFailedEvent::class => [
                ['endSpanOnError', -10000],
            ],
            WorkerMessageHandledEvent::class => [
                ['endSpanWithSuccess', -10000],
            ],
        ];
    }

    /**
     * @return class-string[]
     */
    public static function getSubscribedServices(): array
    {
        return [TracerInterface::class];
    }

    public function startSpan(WorkerMessageReceivedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        $traceable = $this->parseAttribute($message);

        if (!$this->isAutoTraceable() && !$this->isAttributeTraceable($traceable)) {
            $this->logger?->debug(sprintf('Message "%s" is not traceable, skipping span creation', get_class($message)));

            return;
        }

        // Clean up any lingering scope from a previous message that was not
        // properly ended (e.g. an exception in another high-priority subscriber
        // prevented the handled/failed event from firing).
        $previousScope = Context::storage()->scope();
        if (null !== $previousScope) {
            $previousScope->detach();
            $orphanedSpan = Span::fromContext($previousScope->context());
            $orphanedSpan->setStatus(StatusCode::STATUS_ERROR, 'Span was not properly ended');
            $orphanedSpan->end();
            $this->logger?->warning(sprintf('Cleaned up orphaned span "%s"', $orphanedSpan->getContext()->getSpanId()));
        }

        // ensure propagation from incoming trace
        $context = $this->propagator->extract($event->getEnvelope(), $this->traceStampPropagator);

        $messageClass = get_class($message);

        $span = $this->getTracer($traceable)
            ->spanBuilder(sprintf('%s %s', $event->getReceiverName(), $messageClass))
            ->setParent($context)
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setAttribute('messaging.operation.type', 'process')
            ->setAttribute('messaging.destination.name', $event->getReceiverName())
            ->startSpan();

        $busNameStamp = $event->getEnvelope()->last(BusNameStamp::class);

        if (null !== $busNameStamp) {
            $span->setAttribute('symfony.messenger.bus.name', $busNameStamp->getBusName());
        }

        $this->logger?->debug(sprintf('Starting span "%s"', $span->getContext()->getSpanId()));

        Context::storage()
            ->attach(
                $span->storeInContext($context)
            )
        ;
    }

    public function endSpanWithSuccess(WorkerMessageHandledEvent $event): void
    {
        $scope = Context::storage()->scope();

        if (null === $scope) {
            $this->logger?->debug('No active scope');

            return;
        }

        $scope->detach();

        $span = Span::fromContext($scope->context());
        $span->setStatus(StatusCode::STATUS_OK);
        $this->logger?->debug(sprintf('Ending span "%s"', $span->getContext()->getSpanId()));
        $span->end();
    }

    public function endSpanOnError(WorkerMessageFailedEvent $event): void
    {
        $scope = Context::storage()->scope();

        if (null === $scope) {
            $this->logger?->debug('No active scope');

            return;
        }

        $scope->detach();

        $span = Span::fromContext($scope->context());

        $span->setAttribute('symfony.messenger.will_retry', $event->willRetry());

        $redeliveryStamp = $event->getEnvelope()->last(RedeliveryStamp::class);
        if (null !== $redeliveryStamp) {
            $span->setAttribute('symfony.messenger.retry_count', $redeliveryStamp->getRetryCount());
        }

        $exception = $event->getThrowable();

        if ($exception instanceof WrappedExceptionsInterface) {
            foreach ($exception->getWrappedExceptions() as $nestedException) {
                $span->recordException($nestedException);
            }
        } else {
            $span->recordException($exception);
        }

        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

        $this->logger?->debug(sprintf('Ending span "%s"', $span->getContext()->getSpanId()));
        $span->end();
    }

    private function parseAttribute(object $message): ?Traceable
    {
        $reflection = new \ReflectionClass($message);
        $attribute = $reflection->getAttributes(Traceable::class)[0] ?? null;

        return $attribute?->newInstance();
    }

    private function getTracer(?Traceable $traceable): TracerInterface
    {
        if (null !== $traceable?->tracer) {
            if (!$this->tracerLocator->has($traceable->tracer)) {
                $this->logger?->warning(sprintf('Tracer "%s" not found in service locator, using default tracer', $traceable->tracer));

                return $this->tracer;
            }

            return $this->tracerLocator->get($traceable->tracer);
        }

        return $this->tracer;
    }

    private function isAutoTraceable(): bool
    {
        return InstrumentationTypeEnum::Auto === $this->instrumentationType;
    }

    private function isAttributeTraceable(?Traceable $traceable): bool
    {
        return InstrumentationTypeEnum::Attribute === $this->instrumentationType
            && null !== $traceable;
    }
}
