<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\TracerProvider;

use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

/**
 * Returns the TracerProvider currently registered in OpenTelemetry\API\Globals. The arguments are
 * accepted to honour the factory contract but ignored — provider construction (sampler, processors,
 * resource) is the responsibility of whichever bootstrap publishes into Globals, typically
 * OTEL_PHP_AUTOLOAD_ENABLED=true or a manual Sdk::builder()->buildAndRegisterGlobal() call.
 */
final readonly class GlobalsTracerProviderFactory extends AbstractTracerProviderFactory
{
    public function createProvider(?SamplerInterface $sampler = null, array $processors = [], ?ResourceInfo $info = null): TracerProviderInterface
    {
        $provider = Globals::tracerProvider();
        if (!$provider instanceof TracerProviderInterface) {
            throw new \LogicException(sprintf('OpenTelemetry\\API\\Globals returned a TracerProvider of type %s, which does not implement the SDK TracerProviderInterface. Ensure the OpenTelemetry SDK is bootstrapped (e.g. OTEL_PHP_AUTOLOAD_ENABLED=true) before this bundle resolves provider services.', $provider::class));
        }

        return $provider;
    }
}
