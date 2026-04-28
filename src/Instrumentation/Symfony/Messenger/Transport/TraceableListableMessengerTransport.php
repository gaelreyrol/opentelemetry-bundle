<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransport;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

final class TraceableListableMessengerTransport extends TraceableMessengerTransport implements ListableReceiverInterface
{
    use ForwardsListable;
}
