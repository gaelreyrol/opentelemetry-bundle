<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Console;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Attribute\Traceable;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\InstrumentationTypeEnum;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\InstrumentationTypeInterface;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Attribute\ConsoleTraceAttributeEnum;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class TraceableConsoleEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface, InstrumentationTypeInterface
{
    private InstrumentationTypeEnum $instrumentationType = InstrumentationTypeEnum::Auto;
    /**
     * @var string[]
     */
    private array $excludeCommands = [];

    public function __construct(
        private readonly TracerInterface $tracer,
        /** @var ServiceLocator<TracerInterface> */
        private readonly ServiceLocator $tracerLocator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => [
                ['startSpan', 10000],
            ],
            ConsoleEvents::ERROR => [
                ['handleError', -10000],
            ],
            ConsoleEvents::TERMINATE => [
                ['endSpan', -10000],
            ],
            ConsoleEvents::SIGNAL => [
                ['handleSignal', -10000],
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

    public function startSpan(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        assert($command instanceof Command);

        if (false === $this->isAutoTraceable($command) && false === $this->isAttributeTraceable($command)) {
            return;
        }

        $tracer = $this->getTracer($command);

        $name = $command->getName();
        $class = get_class($command);

        $spanBuilder = $tracer
            ->spanBuilder($name)
            ->setAttributes([
                CodeAttributes::CODE_FUNCTION_NAME => $class.'::execute',
            ]);

        $parent = Context::getCurrent();

        $span = $spanBuilder->setParent($parent)->startSpan();

        $this->logger?->debug(sprintf('Starting span "%s"', $span->getContext()->getSpanId()));

        Context::storage()->attach($span->storeInContext($parent));

        $this->logger?->debug(sprintf('Activating new scope "%s"', spl_object_id(Context::storage()->scope())));
    }

    public function handleError(ConsoleErrorEvent $event): void
    {
        $span = Span::getCurrent();
        $span->setStatus(StatusCode::STATUS_ERROR);
        $span->recordException($event->getError(), [
            ConsoleTraceAttributeEnum::ExitCode->toString() => $event->getExitCode(),
        ]);
    }

    public function endSpan(ConsoleTerminateEvent $event): void
    {
        $scope = Context::storage()->scope();
        if (null === $scope) {
            $this->logger?->debug('No active scope');

            return;
        }
        $this->logger?->debug(sprintf('Detaching scope "%s"', spl_object_id($scope)));
        $scope->detach();

        $span = Span::fromContext($scope->context());
        $span->setAttribute(
            ConsoleTraceAttributeEnum::ExitCode->value,
            $event->getExitCode()
        );

        $statusCode = match ($event->getExitCode()) {
            Command::SUCCESS => StatusCode::STATUS_OK,
            default => StatusCode::STATUS_ERROR,
        };
        $span->setStatus($statusCode);

        $this->logger?->debug(sprintf('Ending span "%s"', $span->getContext()->getSpanId()));
        $span->end();
    }

    public function handleSignal(ConsoleSignalEvent $event): void
    {
        $span = Span::getCurrent();
        $span->setAttribute(ConsoleTraceAttributeEnum::SignalCode->toString(), $event->getHandlingSignal());
    }

    private function parseAttribute(Command $command): ?Traceable
    {
        $reflection = new \ReflectionClass($command);
        $attribute = $reflection->getAttributes(Traceable::class)[0] ?? null;

        return $attribute?->newInstance();
    }

    private function getTracer(Command $command): TracerInterface
    {
        $traceable = $this->parseAttribute($command);

        if (null !== $traceable?->tracer) {
            return $this->tracerLocator->get($traceable->tracer);
        }

        return $this->tracer;
    }

    private function isAutoTraceable(Command $command): bool
    {
        if (InstrumentationTypeEnum::Auto !== $this->instrumentationType) {
            return false;
        }

        if (0 === count($this->excludeCommands)) {
            return true;
        }

        $combinedExcludeCommands = implode('|', $this->excludeCommands);
        if (preg_match("#{$combinedExcludeCommands}#", $command->getName())) {
            return false;
        }

        return true;
    }

    private function isAttributeTraceable(Command $command): bool
    {
        $traceable = $this->parseAttribute($command);

        return InstrumentationTypeEnum::Attribute === $this->instrumentationType
            && true === $traceable instanceof Traceable;
    }

    public function setInstrumentationType(InstrumentationTypeEnum $type): void
    {
        $this->instrumentationType = $type;
    }

    /**
     * @param string[] $excludeCommands
     */
    public function setExcludeCommands(array $excludeCommands): void
    {
        $this->excludeCommands = $excludeCommands;
    }
}
