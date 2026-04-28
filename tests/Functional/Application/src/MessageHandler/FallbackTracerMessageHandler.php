<?php

namespace App\MessageHandler;

use App\Message\FallbackTracerMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FallbackTracerMessageHandler
{
    public function __invoke(FallbackTracerMessage $message): string
    {
        return $message->name;
    }
}
