<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\TraceStampPropagator;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Injects the current trace context into the Messenger envelope as a TraceStamp
 * so that trace propagation is maintained across asynchronous message boundaries.
 */
final readonly class AddStampForPropagationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MultiTextMapPropagator $propagator,
        private TraceStampPropagator $traceStampPropagator,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $traceStamp = $envelope->last(TraceStamp::class);

        if (null !== $traceStamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $scope = Context::storage()->scope();

        if (null !== $scope) {
            // inject() mutates $envelope by reference through the TraceStampPropagator setter,
            // because Envelope is immutable and with() returns a new instance.
            $this->propagator->inject($envelope, $this->traceStampPropagator, Context::getCurrent());
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
