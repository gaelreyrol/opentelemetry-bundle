<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransport;
use Symfony\Component\Messenger\Transport\CloseableTransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

final class TraceableKeepaliveMessengerTransport extends TraceableMessengerTransport implements CloseableTransportInterface, KeepaliveReceiverInterface, MessageCountAwareInterface, SetupableTransportInterface
{
    use ForwardsCloseable;
    use ForwardsKeepalive;
    use ForwardsMessageCount;
    use ForwardsSetup;
}
