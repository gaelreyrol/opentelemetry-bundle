<?php

namespace App\Message;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Attribute\Traceable;

#[Traceable(tracer: 'open_telemetry.traces.tracers.fallback')]
final readonly class FallbackTracerMessage
{
    public function __construct(
        public string $name,
    ) {
    }
}
