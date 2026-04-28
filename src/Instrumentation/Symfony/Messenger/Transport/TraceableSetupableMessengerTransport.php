<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransport;
use Symfony\Component\Messenger\Transport\CloseableTransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

final class TraceableSetupableMessengerTransport extends TraceableMessengerTransport implements CloseableTransportInterface, MessageCountAwareInterface, SetupableTransportInterface
{
    use ForwardsCloseable;
    use ForwardsMessageCount;
    use ForwardsSetup;
}
