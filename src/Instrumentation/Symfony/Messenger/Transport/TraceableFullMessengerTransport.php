<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransport;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

/**
 * Capability shape used by Symfony's DoctrineTransport: Setupable + CountAware + Listable + Keepalive.
 */
final class TraceableFullMessengerTransport extends TraceableMessengerTransport implements KeepaliveReceiverInterface, ListableReceiverInterface, MessageCountAwareInterface, SetupableTransportInterface
{
    use ForwardsKeepalive;
    use ForwardsListable;
    use ForwardsMessageCount;
    use ForwardsSetup;
}
