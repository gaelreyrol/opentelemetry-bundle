<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\Instrumentation\Symfony\Messenger;

use App\Message\FallbackTracerMessage;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\InstrumentationTypeEnum;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\WorkerMessageEventSubscriber;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\TraceStampPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

#[CoversClass(WorkerMessageEventSubscriber::class)]
class WorkerMessageEventSubscriberTest extends TestCase
{
    private InMemoryExporter $exporter;
    private TracerInterface $tracer;
    private MockObject&LoggerInterface $logger;
    private WorkerMessageEventSubscriber $subscriber;

    protected function setUp(): void
    {
        while (null !== ($scope = Context::storage()->scope())) {
            $scope->detach();
        }

        $this->exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->exporter));
        $this->tracer = $tracerProvider->getTracer('test');
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new WorkerMessageEventSubscriber(
            new MultiTextMapPropagator([]),
            $this->tracer,
            new ServiceLocator([]),
            new TraceStampPropagator(),
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        while (null !== ($scope = Context::storage()->scope())) {
            $scope->detach();
        }
    }

    public function testGetSubscribedEventsReturnsCorrectMap(): void
    {
        $events = WorkerMessageEventSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(WorkerMessageReceivedEvent::class, $events);
        self::assertArrayHasKey(WorkerMessageFailedEvent::class, $events);
        self::assertArrayHasKey(WorkerMessageHandledEvent::class, $events);

        self::assertSame([['startSpan', 10000]], $events[WorkerMessageReceivedEvent::class]);
        self::assertSame([['endSpanOnError', -10000]], $events[WorkerMessageFailedEvent::class]);
        self::assertSame([['endSpanWithSuccess', -10000]], $events[WorkerMessageHandledEvent::class]);
    }

    public function testGetSubscribedServicesReturnsTracerInterface(): void
    {
        $services = WorkerMessageEventSubscriber::getSubscribedServices();

        self::assertSame([TracerInterface::class], $services);
    }

    public function testStartSpanCreatesConsumerSpan(): void
    {
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageReceivedEvent($envelope, 'main');

        $this->subscriber->startSpan($event);

        // Verify scope was attached
        $scope = Context::storage()->scope();
        self::assertNotNull($scope);

        // End the span via the handled event
        $this->subscriber->endSpanWithSuccess(new WorkerMessageHandledEvent($envelope, 'main'));

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame('main stdClass', $spans[0]->getName());
        self::assertSame(SpanKind::KIND_CONSUMER, $spans[0]->getKind());
        self::assertSame(StatusCode::STATUS_OK, $spans[0]->getStatus()->getCode());
    }

    public function testStartSpanSkipsWhenNotTraceable(): void
    {
        $this->subscriber->setInstrumentationType(InstrumentationTypeEnum::Attribute);

        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageReceivedEvent($envelope, 'main');

        $this->subscriber->startSpan($event);

        self::assertNull(Context::storage()->scope());
        self::assertCount(0, $this->exporter->getSpans());
    }

    public function testEndSpanWithSuccessLogsWhenNoScope(): void
    {
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('No active scope');

        $envelope = new Envelope(new \stdClass());
        $this->subscriber->endSpanWithSuccess(new WorkerMessageHandledEvent($envelope, 'main'));

        self::assertCount(0, $this->exporter->getSpans());
    }

    public function testEndSpanOnErrorLogsWhenNoScope(): void
    {
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('No active scope');

        $envelope = new Envelope(new \stdClass());
        $exception = new \RuntimeException('fail');
        $this->subscriber->endSpanOnError(new WorkerMessageFailedEvent($envelope, 'main', $exception));

        self::assertCount(0, $this->exporter->getSpans());
    }

    public function testGetTracerFallsBackWhenTracerNotInLocator(): void
    {
        $fallbackTracer = (new TracerProvider(new SimpleSpanProcessor($this->exporter)))->getTracer('fallback');

        $subscriber = new WorkerMessageEventSubscriber(
            new MultiTextMapPropagator([]),
            $fallbackTracer,
            new ServiceLocator([]),
            new TraceStampPropagator(),
            $this->logger,
        );
        $subscriber->setInstrumentationType(InstrumentationTypeEnum::Attribute);

        // FallbackTracerMessage has #[Traceable(tracer: 'open_telemetry.traces.tracers.fallback')]
        // but the locator is empty, so it should fall back to the default tracer with a warning
        $message = new FallbackTracerMessage('test');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(self::stringContains('not found in service locator'));

        $envelope = new Envelope($message);
        $subscriber->startSpan(new WorkerMessageReceivedEvent($envelope, 'main'));
        $subscriber->endSpanWithSuccess(new WorkerMessageHandledEvent($envelope, 'main'));

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
    }

    public function testDefaultInstrumentationTypeIsAuto(): void
    {
        // Without calling setInstrumentationType, the subscriber should use Auto mode
        $envelope = new Envelope(new \stdClass());

        $this->subscriber->startSpan(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->subscriber->endSpanWithSuccess(new WorkerMessageHandledEvent($envelope, 'main'));

        self::assertCount(1, $this->exporter->getSpans());
    }
}
