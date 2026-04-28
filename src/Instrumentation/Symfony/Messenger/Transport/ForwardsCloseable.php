<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use Symfony\Component\Messenger\Transport\CloseableTransportInterface;

/**
 * @internal
 */
trait ForwardsCloseable
{
    public function close(): void
    {
        \assert($this->transport instanceof CloseableTransportInterface);

        $this->transport->close();
    }
}
