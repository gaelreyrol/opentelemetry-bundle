<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Instrumentation\Messenger;

use App\Kernel;
use App\Message\DummyMessage;
use App\Message\FallbackTracerMessage;
use App\Message\TraceableMessage;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\TracingTestCaseTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\StatusData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
class MessengerWorkerAttributeTracingTest extends KernelTestCase
{
    use TracingTestCaseTrait;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->eventDispatcher = self::getContainer()->get('event_dispatcher');
    }

    public function testAttributeModeIgnoresNonTraceableMessage(): void
    {
        $envelope = new Envelope(new DummyMessage('test'));

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'main'));

        self::assertSpansCount(0);
    }

    public function testAttributeModeCreatesSpanForTraceableMessage(): void
    {
        $envelope = new Envelope(new TraceableMessage('test'));

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'main'));

        self::assertSpansCount(1);

        $span = self::getSpans()[0];
        self::assertSpanName($span, 'main App\Message\TraceableMessage');
        self::assertSpanStatus($span, StatusData::ok());
        self::assertSame(SpanKind::KIND_CONSUMER, $span->getKind());
        self::assertSpanAttributesSubSet($span, [
            'messaging.operation.type' => 'process',
            'messaging.destination.name' => 'main',
        ]);
    }

    public function testAttributeModeWithCustomTracer(): void
    {
        $envelope = new Envelope(new FallbackTracerMessage('test'));

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'main'));

        self::assertSpansCount(0);

        self::assertSpansCount(1, 'open_telemetry.traces.exporters.fallback');

        $span = self::getSpans('open_telemetry.traces.exporters.fallback')[0];
        self::assertSpanName($span, 'main App\Message\FallbackTracerMessage');
        self::assertSpanStatus($span, StatusData::ok());
        self::assertSame(SpanKind::KIND_CONSUMER, $span->getKind());
        self::assertSpanAttributesSubSet($span, [
            'messaging.operation.type' => 'process',
            'messaging.destination.name' => 'main',
        ]);
    }
}
