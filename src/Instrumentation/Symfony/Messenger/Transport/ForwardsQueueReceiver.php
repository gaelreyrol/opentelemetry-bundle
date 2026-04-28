<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;

/**
 * @internal
 */
trait ForwardsQueueReceiver
{
    /**
     * @param string[] $queueNames
     *
     * @return iterable<Envelope>
     */
    public function getFromQueues(array $queueNames): iterable
    {
        \assert($this->transport instanceof QueueReceiverInterface);

        return $this->transport->getFromQueues($queueNames);
    }
}
