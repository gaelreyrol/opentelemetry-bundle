<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Instrumentation\Messenger;

use App\Kernel;
use App\Message\DummyMessage;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\TracingTestCaseTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\StatusData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
#[Env('APP_ENV', 'auto')]
class MessengerWorkerTracingTest extends KernelTestCase
{
    use TracingTestCaseTrait;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->eventDispatcher = self::getContainer()->get('event_dispatcher');
    }

    public function testWorkerMessageHandled(): void
    {
        $envelope = new Envelope(new DummyMessage('test'), [new BusNameStamp('messenger.bus.default')]);

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'main'));

        self::assertSpansCount(1);

        $span = self::getSpans()[0];
        self::assertSpanName($span, 'main App\Message\DummyMessage');
        self::assertSpanStatus($span, StatusData::ok());
        self::assertSame(SpanKind::KIND_CONSUMER, $span->getKind());
        self::assertSpanAttributesSubSet($span, [
            'messaging.operation.type' => 'process',
            'messaging.destination.name' => 'main',
            'symfony.messenger.bus.name' => 'messenger.bus.default',
        ]);
        self::assertSpanEventsCount($span, 0);
    }

    public function testWorkerMessageHandledWithoutBusNameStamp(): void
    {
        $envelope = new Envelope(new DummyMessage('test'));

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'main'));

        self::assertSpansCount(1);

        $span = self::getSpans()[0];
        self::assertSpanName($span, 'main App\Message\DummyMessage');
        self::assertSpanStatus($span, StatusData::ok());
        self::assertSpanAttributes($span, [
            'messaging.operation.type' => 'process',
            'messaging.destination.name' => 'main',
        ]);
    }

    public function testWorkerMessageFailed(): void
    {
        $envelope = new Envelope(new DummyMessage('test'), [new BusNameStamp('messenger.bus.default')]);
        $exception = new \RuntimeException('Something went wrong');

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'main', $exception));

        self::assertSpansCount(1);

        $span = self::getSpans()[0];
        self::assertSpanName($span, 'main App\Message\DummyMessage');
        self::assertSpanStatus($span, new StatusData(StatusCode::STATUS_ERROR, 'Something went wrong'));
        self::assertSame(SpanKind::KIND_CONSUMER, $span->getKind());
        self::assertSpanAttributesSubSet($span, [
            'messaging.operation.type' => 'process',
            'messaging.destination.name' => 'main',
            'symfony.messenger.bus.name' => 'messenger.bus.default',
            'symfony.messenger.will_retry' => false,
        ]);
        self::assertSpanEventsCount($span, 1);

        $exceptionEvent = $span->getEvents()[0];
        self::assertSpanEventName($exceptionEvent, 'exception');
        self::assertSpanEventAttributesSubSet($exceptionEvent, [
            'exception.type' => 'RuntimeException',
            'exception.message' => 'Something went wrong',
        ]);
    }

    public function testWorkerMessageFailedWithPreviousException(): void
    {
        $envelope = new Envelope(new DummyMessage('test'));
        $previous = new \LogicException('Root cause');
        $exception = new \RuntimeException('Something went wrong', 0, $previous);

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'main', $exception));

        self::assertSpansCount(1);

        $span = self::getSpans()[0];
        self::assertSpanStatus($span, new StatusData(StatusCode::STATUS_ERROR, 'Something went wrong'));
        self::assertSpanEventsCount($span, 1);

        $exceptionEvent = $span->getEvents()[0];
        self::assertSpanEventName($exceptionEvent, 'exception');
        self::assertSpanEventAttributesSubSet($exceptionEvent, [
            'exception.type' => 'RuntimeException',
            'exception.message' => 'Something went wrong',
        ]);
    }

    public function testWorkerMessageFailedWithHandlerFailedException(): void
    {
        $envelope = new Envelope(new DummyMessage('test'));
        $nested1 = new \RuntimeException('Handler A failed');
        $nested2 = new \LogicException('Handler B failed');
        $exception = new HandlerFailedException($envelope, [$nested1, $nested2]);

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'main', $exception));

        self::assertSpansCount(1);

        $span = self::getSpans()[0];
        self::assertSpanStatus($span, new StatusData(StatusCode::STATUS_ERROR, $exception->getMessage()));
        self::assertSpanEventsCount($span, 2);

        $event1 = $span->getEvents()[0];
        self::assertSpanEventName($event1, 'exception');
        self::assertSpanEventAttributesSubSet($event1, [
            'exception.type' => 'RuntimeException',
            'exception.message' => 'Handler A failed',
        ]);

        $event2 = $span->getEvents()[1];
        self::assertSpanEventName($event2, 'exception');
        self::assertSpanEventAttributesSubSet($event2, [
            'exception.type' => 'LogicException',
            'exception.message' => 'Handler B failed',
        ]);
    }

    public function testWorkerMessageFailedWillRetry(): void
    {
        $envelope = new Envelope(new DummyMessage('test'), [new BusNameStamp('messenger.bus.default')]);
        $exception = new \RuntimeException('Transient failure');

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));

        $failedEvent = new WorkerMessageFailedEvent($envelope, 'main', $exception);
        $failedEvent->setForRetry();
        $this->eventDispatcher->dispatch($failedEvent);

        self::assertSpansCount(1);

        $span = self::getSpans()[0];
        self::assertSpanStatus($span, new StatusData(StatusCode::STATUS_ERROR, 'Transient failure'));
        self::assertSpanAttributesSubSet($span, [
            'symfony.messenger.will_retry' => true,
        ]);
    }

    public function testWorkerMessageFailedWithRedeliveryStamp(): void
    {
        $envelope = new Envelope(new DummyMessage('test'), [
            new BusNameStamp('messenger.bus.default'),
            new RedeliveryStamp(3),
        ]);
        $exception = new \RuntimeException('Retry exhausted');

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'main', $exception));

        self::assertSpansCount(1);

        $span = self::getSpans()[0];
        self::assertSpanStatus($span, new StatusData(StatusCode::STATUS_ERROR, 'Retry exhausted'));
        self::assertSpanAttributesSubSet($span, [
            'symfony.messenger.will_retry' => false,
            'symfony.messenger.retry_count' => 3,
        ]);
    }

    public function testLingeringScopeIsCleanedOnNextMessage(): void
    {
        $envelope1 = new Envelope(new DummyMessage('first'));
        $envelope2 = new Envelope(new DummyMessage('second'));

        // First message starts a span but is never handled/failed
        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope1, 'main'));

        // Second message arrives — should clean up the orphaned span
        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelope2, 'main'));
        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope2, 'main'));

        self::assertSpansCount(2);

        $orphanedSpan = self::getSpans()[0];
        self::assertSpanName($orphanedSpan, 'main App\Message\DummyMessage');
        self::assertSpanStatus($orphanedSpan, new StatusData(StatusCode::STATUS_ERROR, 'Span was not properly ended'));

        $normalSpan = self::getSpans()[1];
        self::assertSpanName($normalSpan, 'main App\Message\DummyMessage');
        self::assertSpanStatus($normalSpan, StatusData::ok());
    }
}
