<?php

namespace App\Message;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Attribute\Traceable;

#[Traceable]
final readonly class TraceableMessage
{
    public function __construct(
        public string $name,
    ) {
    }
}
