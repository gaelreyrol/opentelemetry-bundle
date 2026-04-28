<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
trait ForwardsResettable
{
    public function reset(): void
    {
        \assert($this->transport instanceof ResetInterface);

        $this->transport->reset();
    }
}
