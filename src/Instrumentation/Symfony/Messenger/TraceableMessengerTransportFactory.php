<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableFullMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableInMemoryMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableKeepaliveMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableListableMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableQueueMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableSetupableMessengerTransport;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\LoggerInterface;
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
use Zenstruck\Dsn;

/**
 * @implements TransportFactoryInterface<TraceableMessengerTransport>
 */
class TraceableMessengerTransportFactory implements TransportFactoryInterface
{
    /**
     * Capability interfaces inspected when picking a polymorphic decorator.
     * Used to enumerate the unforwarded interfaces of an unrecognized transport.
     *
     * @var list<class-string>
     */
    private const CAPABILITY_INTERFACES = [
        SetupableTransportInterface::class,
        CloseableTransportInterface::class,
        MessageCountAwareInterface::class,
        ListableReceiverInterface::class,
        QueueReceiverInterface::class,
        KeepaliveReceiverInterface::class,
        ResetInterface::class,
    ];

    public function __construct(
        private TransportFactory $transportFactory,
        private TracerInterface $tracer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<mixed> $options
     */
    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $transport = $this->transportFactory->createTransport(Dsn::parse($dsn)->inner(), $options, $serializer);

        return $this->decorate($transport);
    }

    /**
     * @param array<mixed> $options
     */
    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        $dsn = Dsn::parse($dsn);
        if (!$dsn instanceof Dsn\Decorated) {
            return false;
        }

        return $dsn->scheme()->equals('trace');
    }

    private function decorate(TransportInterface $transport): TraceableMessengerTransport
    {
        $isSetupable = $transport instanceof SetupableTransportInterface;
        $isCloseable = $transport instanceof CloseableTransportInterface;
        $isCountAware = $transport instanceof MessageCountAwareInterface;
        $isListable = $transport instanceof ListableReceiverInterface;
        $isQueue = $transport instanceof QueueReceiverInterface;
        $isKeepalive = $transport instanceof KeepaliveReceiverInterface;
        $isResettable = $transport instanceof ResetInterface;

        // Doctrine-shape: Setupable + CountAware + Listable + Keepalive.
        if ($isSetupable && $isCountAware && $isListable && $isKeepalive && !$isCloseable && !$isQueue && !$isResettable) {
            return new TraceableFullMessengerTransport($transport, $this->tracer, $this->logger);
        }

        // Redis-shape: Setupable + CountAware + Keepalive + Closeable.
        if ($isSetupable && $isCloseable && $isCountAware && $isKeepalive && !$isListable && !$isQueue && !$isResettable) {
            return new TraceableKeepaliveMessengerTransport($transport, $this->tracer, $this->logger);
        }

        // AMQP-shape: Setupable + CountAware + QueueReceiver + Closeable.
        if ($isSetupable && $isCloseable && $isCountAware && $isQueue && !$isListable && !$isKeepalive && !$isResettable) {
            return new TraceableQueueMessengerTransport($transport, $this->tracer, $this->logger);
        }

        // SQS / Beanstalkd-shape: Setupable + CountAware + Closeable.
        if ($isSetupable && $isCloseable && $isCountAware && !$isListable && !$isQueue && !$isKeepalive && !$isResettable) {
            return new TraceableSetupableMessengerTransport($transport, $this->tracer, $this->logger);
        }

        // InMemory-shape: Reset only.
        if ($isResettable && !$isSetupable && !$isCloseable && !$isCountAware && !$isListable && !$isQueue && !$isKeepalive) {
            return new TraceableInMemoryMessengerTransport($transport, $this->tracer, $this->logger);
        }

        // FailureTransport-shape: Listable only.
        if ($isListable && !$isSetupable && !$isCloseable && !$isCountAware && !$isQueue && !$isKeepalive && !$isResettable) {
            return new TraceableListableMessengerTransport($transport, $this->tracer, $this->logger);
        }

        // No additional capabilities — base decorator is faithful.
        if (!$isSetupable && !$isCloseable && !$isCountAware && !$isListable && !$isQueue && !$isKeepalive && !$isResettable) {
            return new TraceableMessengerTransport($transport, $this->tracer, $this->logger);
        }

        // Unrecognized capability shape: warn so users can open an issue, then fall back to the base decorator
        // so we never make the situation worse than today (where every capability is silently stripped).
        $unforwarded = array_values(array_filter(
            self::CAPABILITY_INTERFACES,
            static fn (string $iface) => $transport instanceof $iface,
        ));

        $this->logger?->warning(
            'The "trace(...)" Messenger transport decorator does not know how to forward this transport\'s capability interfaces; they will be stripped. Please open an issue at https://github.com/FriendsOfOpenTelemetry/opentelemetry-bundle/issues with the inner transport class.',
            [
                'inner_transport' => $transport::class,
                'unforwarded_interfaces' => $unforwarded,
            ],
        );

        return new TraceableMessengerTransport($transport, $this->tracer, $this->logger);
    }
}
