<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransport;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Capability shape used by Symfony's InMemoryTransport: Reset only.
 */
final class TraceableInMemoryMessengerTransport extends TraceableMessengerTransport implements ResetInterface
{
    use ForwardsResettable;
}
