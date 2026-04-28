<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\Instrumentation\Symfony\Messenger;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableFullMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableInMemoryMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableQueueMessengerTransport;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\CloseableTransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(TraceableFullMessengerTransport::class)]
#[CoversClass(TraceableInMemoryMessengerTransport::class)]
#[CoversClass(TraceableQueueMessengerTransport::class)]
class TraceableMessengerTransportCapabilitiesTest extends TestCase
{
    private InMemoryExporter $exporter;
    private TracerProvider $tracerProvider;

    protected function setUp(): void
    {
        $this->exporter = new InMemoryExporter();
        $this->tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->exporter));
    }

    public function testFullDecoratorForwardsAllCapabilities(): void
    {
        $envelope = new Envelope(new \stdClass());
        $inner = new class($envelope) implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface, KeepaliveReceiverInterface {
            public bool $setupCalled = false;
            public int $countCalls = 0;
            /** @var list<int|null> */
            public array $allLimits = [];
            /** @var list<mixed> */
            public array $findIds = [];
            /** @var list<int|null> */
            public array $keepaliveSeconds = [];

            public function __construct(private Envelope $sample)
            {
            }

            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                return $envelope;
            }

            public function setup(): void
            {
                $this->setupCalled = true;
            }

            public function getMessageCount(): int
            {
                ++$this->countCalls;

                return 42;
            }

            public function all(?int $limit = null): iterable
            {
                $this->allLimits[] = $limit;

                return [$this->sample];
            }

            public function find(mixed $id): ?Envelope
            {
                $this->findIds[] = $id;

                return 'missing' === $id ? null : $this->sample;
            }

            public function keepalive(Envelope $envelope, ?int $seconds = null): void
            {
                $this->keepaliveSeconds[] = $seconds;
            }
        };

        $decorator = new TraceableFullMessengerTransport($inner, $this->tracerProvider->getTracer('test'));

        $decorator->setup();
        self::assertSame(42, $decorator->getMessageCount());
        self::assertSame([$envelope], iterator_to_array((function () use ($decorator) { yield from $decorator->all(7); })()));
        self::assertSame($envelope, $decorator->find('id-1'));
        $decorator->keepalive($envelope, 30);

        self::assertTrue($inner->setupCalled);
        self::assertSame(1, $inner->countCalls);
        self::assertSame([7], $inner->allLimits);
        self::assertSame(['id-1'], $inner->findIds);
        self::assertSame([30], $inner->keepaliveSeconds);

        // None of these capability methods should produce spans.
        self::assertSame([], $this->exporter->getSpans());
    }

    public function testInMemoryDecoratorForwardsResetAndDoesNotForwardCloseOrSetup(): void
    {
        $inner = new class implements TransportInterface, ResetInterface {
            public bool $resetCalled = false;

            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                return $envelope;
            }

            public function reset(): void
            {
                $this->resetCalled = true;
            }
        };

        $decorator = new TraceableInMemoryMessengerTransport($inner, $this->tracerProvider->getTracer('test'));

        $decorator->reset();

        self::assertTrue($inner->resetCalled);
        self::assertSame([], $this->exporter->getSpans());
    }

    public function testQueueDecoratorForwardsGetFromQueues(): void
    {
        $envelope = new Envelope(new \stdClass());
        $inner = new class($envelope) implements TransportInterface, SetupableTransportInterface, CloseableTransportInterface, MessageCountAwareInterface, QueueReceiverInterface {
            /** @var list<array<int, string>> */
            public array $queueCalls = [];

            public function __construct(private Envelope $sample)
            {
            }

            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                return $envelope;
            }

            public function setup(): void
            {
            }

            public function close(): void
            {
            }

            public function getMessageCount(): int
            {
                return 0;
            }

            public function getFromQueues(array $queueNames): iterable
            {
                $this->queueCalls[] = $queueNames;

                return [$this->sample];
            }
        };

        $decorator = new TraceableQueueMessengerTransport($inner, $this->tracerProvider->getTracer('test'));

        $result = $decorator->getFromQueues(['priority', 'default']);

        self::assertSame([$envelope], iterator_to_array((function () use ($result) { yield from $result; })()));
        self::assertSame([['priority', 'default']], $inner->queueCalls);
        self::assertSame([], $this->exporter->getSpans());
    }
}
