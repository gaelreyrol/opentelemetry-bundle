<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\Instrumentation\Symfony\Messenger;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransportFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableFullMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableInMemoryMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableKeepaliveMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableListableMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableQueueMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableSetupableMessengerTransport;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\CloseableTransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(TraceableMessengerTransportFactory::class)]
class TraceableMessengerTransportFactoryTest extends TestCase
{
    /**
     * @return array<string, array{TransportInterface, class-string<TraceableMessengerTransport>}>
     */
    public static function provideTransportShapes(): array
    {
        return [
            'plain transport -> base decorator' => [
                MessengerTransportStubs::plain(),
                TraceableMessengerTransport::class,
            ],
            'doctrine shape -> full' => [
                MessengerTransportStubs::doctrineShape(),
                TraceableFullMessengerTransport::class,
            ],
            'redis shape -> keepalive' => [
                MessengerTransportStubs::redisShape(),
                TraceableKeepaliveMessengerTransport::class,
            ],
            'amqp shape -> queue' => [
                MessengerTransportStubs::amqpShape(),
                TraceableQueueMessengerTransport::class,
            ],
            'sqs shape -> setupable' => [
                MessengerTransportStubs::sqsShape(),
                TraceableSetupableMessengerTransport::class,
            ],
            'in-memory shape -> in-memory' => [
                MessengerTransportStubs::inMemoryShape(),
                TraceableInMemoryMessengerTransport::class,
            ],
            'failure shape -> listable' => [
                MessengerTransportStubs::failureShape(),
                TraceableListableMessengerTransport::class,
            ],
        ];
    }

    #[DataProvider('provideTransportShapes')]
    public function testCreateTransportSelectsTheRightDecorator(TransportInterface $inner, string $expected): void
    {
        $factory = $this->buildFactory($inner);

        $result = $factory->createTransport('trace(in-memory://default)', [], self::createStub(SerializerInterface::class));

        self::assertInstanceOf($expected, $result);
    }

    public function testCreateTransportPreservesInstanceofForKnownInterfaces(): void
    {
        $inner = MessengerTransportStubs::doctrineShape();
        $factory = $this->buildFactory($inner);

        $result = $factory->createTransport('trace(in-memory://default)', [], self::createStub(SerializerInterface::class));

        self::assertInstanceOf(SetupableTransportInterface::class, $result);
        self::assertInstanceOf(MessageCountAwareInterface::class, $result);
        self::assertInstanceOf(ListableReceiverInterface::class, $result);
        self::assertInstanceOf(KeepaliveReceiverInterface::class, $result);
        self::assertNotInstanceOf(CloseableTransportInterface::class, $result);
        self::assertNotInstanceOf(QueueReceiverInterface::class, $result);
        self::assertNotInstanceOf(ResetInterface::class, $result);
    }

    public function testUnknownCapabilityComboFallsBackToBaseAndLogsWarning(): void
    {
        // Setupable + Resettable is not a Symfony first-party shape.
        $inner = MessengerTransportStubs::setupableResettableShape();
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|\Stringable, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };
        $factory = $this->buildFactory($inner, $logger);

        $result = $factory->createTransport('trace(in-memory://default)', [], self::createStub(SerializerInterface::class));

        self::assertSame(TraceableMessengerTransport::class, $result::class);
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertStringContainsString('does not know how to forward', (string) $logger->records[0]['message']);
        self::assertContains(SetupableTransportInterface::class, $logger->records[0]['context']['unforwarded_interfaces']);
        self::assertContains(ResetInterface::class, $logger->records[0]['context']['unforwarded_interfaces']);
    }

    private function buildFactory(TransportInterface $inner, ?LoggerInterface $logger = null): TraceableMessengerTransportFactory
    {
        $innerFactory = new class($inner) implements TransportFactoryInterface {
            public function __construct(private TransportInterface $transport)
            {
            }

            /**
             * @param array<mixed> $options
             */
            public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
            {
                return $this->transport;
            }

            /**
             * @param array<mixed> $options
             */
            public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
            {
                return true;
            }
        };

        return new TraceableMessengerTransportFactory(
            new TransportFactory([$innerFactory]),
            (new TracerProvider())->getTracer('test'),
            $logger,
        );
    }
}

/**
 * @internal
 */
final class MessengerTransportStubs
{
    public static function plain(): TransportInterface
    {
        return new class implements TransportInterface {
            use NoopTransportTrait;
        };
    }

    public static function doctrineShape(): TransportInterface
    {
        return new class implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface, KeepaliveReceiverInterface {
            use NoopTransportTrait;
            use NoopSetupTrait;
            use NoopMessageCountTrait;
            use NoopListableTrait;
            use NoopKeepaliveTrait;
        };
    }

    public static function redisShape(): TransportInterface
    {
        return new class implements TransportInterface, SetupableTransportInterface, CloseableTransportInterface, MessageCountAwareInterface, KeepaliveReceiverInterface {
            use NoopTransportTrait;
            use NoopSetupTrait;
            use NoopCloseTrait;
            use NoopMessageCountTrait;
            use NoopKeepaliveTrait;
        };
    }

    public static function amqpShape(): TransportInterface
    {
        return new class implements TransportInterface, SetupableTransportInterface, CloseableTransportInterface, MessageCountAwareInterface, QueueReceiverInterface {
            use NoopTransportTrait;
            use NoopSetupTrait;
            use NoopCloseTrait;
            use NoopMessageCountTrait;
            use NoopQueueReceiverTrait;
        };
    }

    public static function sqsShape(): TransportInterface
    {
        return new class implements TransportInterface, SetupableTransportInterface, CloseableTransportInterface, MessageCountAwareInterface {
            use NoopTransportTrait;
            use NoopSetupTrait;
            use NoopCloseTrait;
            use NoopMessageCountTrait;
        };
    }

    public static function inMemoryShape(): TransportInterface
    {
        return new class implements TransportInterface, ResetInterface {
            use NoopTransportTrait;
            use NoopResetTrait;
        };
    }

    public static function failureShape(): TransportInterface
    {
        return new class implements TransportInterface, ListableReceiverInterface {
            use NoopTransportTrait;
            use NoopListableTrait;
        };
    }

    public static function setupableResettableShape(): TransportInterface
    {
        return new class implements TransportInterface, SetupableTransportInterface, ResetInterface {
            use NoopTransportTrait;
            use NoopSetupTrait;
            use NoopResetTrait;
        };
    }
}

/**
 * @internal
 */
trait NoopTransportTrait
{
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
}

/**
 * @internal
 */
trait NoopSetupTrait
{
    public function setup(): void
    {
    }
}

/**
 * @internal
 */
trait NoopCloseTrait
{
    public function close(): void
    {
    }
}

/**
 * @internal
 */
trait NoopMessageCountTrait
{
    public function getMessageCount(): int
    {
        return 0;
    }
}

/**
 * @internal
 */
trait NoopListableTrait
{
    public function all(?int $limit = null): iterable
    {
        return [];
    }

    public function find(mixed $id): ?Envelope
    {
        return null;
    }
}

/**
 * @internal
 */
trait NoopQueueReceiverTrait
{
    public function getFromQueues(array $queueNames): iterable
    {
        return [];
    }
}

/**
 * @internal
 */
trait NoopKeepaliveTrait
{
    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
    }
}

/**
 * @internal
 */
trait NoopResetTrait
{
    public function reset(): void
    {
    }
}
