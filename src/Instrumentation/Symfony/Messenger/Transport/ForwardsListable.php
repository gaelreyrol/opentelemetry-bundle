<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * @internal
 */
trait ForwardsListable
{
    /**
     * @return iterable<Envelope>
     */
    public function all(?int $limit = null): iterable
    {
        \assert($this->transport instanceof ListableReceiverInterface);

        return $this->transport->all($limit);
    }

    public function find(mixed $id): ?Envelope
    {
        \assert($this->transport instanceof ListableReceiverInterface);

        return $this->transport->find($id);
    }
}
