<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

/**
 * @internal
 */
trait ForwardsSetup
{
    public function setup(): void
    {
        \assert($this->transport instanceof SetupableTransportInterface);

        $this->transport->setup();
    }
}
