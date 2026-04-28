<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;

/**
 * @internal
 */
trait ForwardsKeepalive
{
    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        \assert($this->transport instanceof KeepaliveReceiverInterface);

        $this->transport->keepalive($envelope, $seconds);
    }
}
