<?php

namespace App\MessageHandler;

use App\Message\TraceableMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TraceableMessageHandler
{
    public function __invoke(TraceableMessage $message): string
    {
        return $message->name;
    }
}
