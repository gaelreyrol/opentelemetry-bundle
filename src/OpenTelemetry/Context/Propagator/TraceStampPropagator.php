<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceStamp;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * Bridges OpenTelemetry's propagation interfaces with Symfony Messenger envelopes,
 * reading and writing trace context via TraceStamp instances attached to the envelope.
 */
final readonly class TraceStampPropagator implements PropagationSetterInterface, PropagationGetterInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function set(&$carrier, string $key, string $value): void
    {
        if (!$carrier instanceof Envelope) {
            throw new \InvalidArgumentException(sprintf('The carrier for trace stamp propagation must be instance of %s', Envelope::class));
        }

        if (TraceContextPropagator::TRACEPARENT === $key) {
            $carrier = $carrier->with(new TraceStamp($value));
            $this->logger?->debug("Trace stamp added to envelope for propagation with traceparent: $value");

            return;
        }

        if (TraceContextPropagator::TRACESTATE === $key) {
            $existing = $carrier->last(TraceStamp::class);

            if (null === $existing) {
                return;
            }

            $carrier = $carrier->with(new TraceStamp($existing->getTraceParent(), $value));
            $this->logger?->debug("Trace stamp updated with tracestate: $value");
        }
    }

    public function keys($carrier): array
    {
        if (!$carrier instanceof Envelope) {
            throw new \InvalidArgumentException(sprintf('The carrier for trace stamp propagation must be instance of %s', Envelope::class));
        }

        return [TraceContextPropagator::TRACEPARENT, TraceContextPropagator::TRACESTATE];
    }

    public function get($carrier, string $key): ?string
    {
        if (!$carrier instanceof Envelope) {
            throw new \InvalidArgumentException(sprintf('The carrier for trace stamp propagation must be instance of %s', Envelope::class));
        }

        $traceStamp = $carrier->last(TraceStamp::class);

        if (null === $traceStamp) {
            return null;
        }

        if (TraceContextPropagator::TRACEPARENT === $key) {
            $traceParent = $traceStamp->getTraceParent();
            $this->logger?->debug("Get trace parent from TraceStamp with value: $traceParent");

            return $traceParent;
        }

        if (TraceContextPropagator::TRACESTATE === $key) {
            return $traceStamp->getTraceState();
        }

        return null;
    }
}
