<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator;

use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;

final class PropagatorFactory
{
    /**
     * Default propagators from https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/#general-sdk-configuration.
     */
    public static function createDefault(): MultiTextMapPropagator
    {
        return new MultiTextMapPropagator([
            BaggagePropagator::getInstance(),
            TraceContextPropagator::getInstance(),
        ]);
    }
}
