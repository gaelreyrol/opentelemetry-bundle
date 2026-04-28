<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @see https://www.w3.org/TR/trace-context/
 */
final readonly class TraceStamp implements StampInterface
{
    public function __construct(
        private string $traceParent,
        private ?string $traceState = null,
    ) {
    }

    public function getTraceParent(): string
    {
        return $this->traceParent;
    }

    public function getTraceState(): ?string
    {
        return $this->traceState;
    }
}
