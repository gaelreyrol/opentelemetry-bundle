<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Instrumentation\Messenger;

use App\Kernel;
use App\Message\DummyMessage;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceStamp;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\TraceStampPropagator;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\TracingTestCaseTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\SDK\Trace\StatusData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
#[Env('APP_ENV', 'auto')]
class MessengerPropagationTracingTest extends KernelTestCase
{
    use TracingTestCaseTrait;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->eventDispatcher = self::getContainer()->get('event_dispatcher');
    }

    public function testWorkerSpanIsChildOfProducerTrace(): void
    {
        /** @var TracerInterface $tracer */
        $tracer = self::getContainer()->get('open_telemetry.traces.tracers.main');

        // Simulate a producer context (e.g. an HTTP request or CLI command)
        $producerSpan = $tracer->spanBuilder('producer-request')->startSpan();
        $producerScope = $producerSpan->activate();

        // Inject current trace context into an envelope via the propagator
        $envelope = new Envelope(new DummyMessage('propagation-test'), [new BusNameStamp('messenger.bus.default')]);

        /** @var MultiTextMapPropagator $propagator */
        $propagator = self::getContainer()->get('open_telemetry.propagator_text_map.multi');

        /** @var TraceStampPropagator $traceStampPropagator */
        $traceStampPropagator = self::getContainer()->get('open_telemetry.instrumentation.messenger.trace_stamp_propagator');
        $propagator->inject($envelope, $traceStampPropagator, Context::getCurrent());

        // End the producer context before worker processing
        $producerScope->detach();
        $producerSpan->end();

        // Assert the TraceStamp was injected
        $traceStamp = $envelope->last(TraceStamp::class);
        self::assertNotNull($traceStamp, 'TraceStamp should be present on the envelope after injection');
        self::assertNotEmpty($traceStamp->getTraceParent());

        // Simulate worker processing with the stamped envelope
        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'main'));

        // Find the producer and consumer spans
        $spans = self::getSpans();
        self::assertCount(2, $spans);

        $producerSpanData = null;
        $consumerSpanData = null;
        foreach ($spans as $span) {
            if ('producer-request' === $span->getName()) {
                $producerSpanData = $span;
            }
            if (SpanKind::KIND_CONSUMER === $span->getKind()) {
                $consumerSpanData = $span;
            }
        }

        self::assertNotNull($producerSpanData, 'Producer span should exist');
        self::assertNotNull($consumerSpanData, 'Consumer span should exist');

        // The consumer span must belong to the same trace
        self::assertSame(
            $producerSpanData->getContext()->getTraceId(),
            $consumerSpanData->getContext()->getTraceId(),
            'Worker span should belong to the same trace as the producer span'
        );

        // The consumer span must be a direct child of the producer span
        self::assertSame(
            $producerSpanData->getContext()->getSpanId(),
            $consumerSpanData->getParentSpanId(),
            'Worker span should be a child of the producer span'
        );

        self::assertSpanStatus($consumerSpanData, StatusData::ok());
    }
}
