<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * @internal
 */
trait ForwardsMessageCount
{
    public function getMessageCount(): int
    {
        \assert($this->transport instanceof MessageCountAwareInterface);

        return $this->transport->getMessageCount();
    }
}
