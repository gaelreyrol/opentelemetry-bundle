<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MeterProvider;

use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilterInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;

/**
 * Returns the MeterProvider currently registered in OpenTelemetry\API\Globals. The arguments are
 * accepted to honour the factory contract but ignored.
 */
final class GlobalsMeterProviderFactory extends AbstractMeterProviderFactory
{
    public function createProvider(?MetricExporterInterface $exporter = null, ?ExemplarFilterInterface $filter = null, ?ResourceInfo $resource = null): MeterProviderInterface
    {
        $provider = Globals::meterProvider();
        if (!$provider instanceof MeterProviderInterface) {
            throw new \LogicException(sprintf('OpenTelemetry\\API\\Globals returned a MeterProvider of type %s, which does not implement the SDK MeterProviderInterface. Ensure the OpenTelemetry SDK is bootstrapped (e.g. OTEL_PHP_AUTOLOAD_ENABLED=true) before this bundle resolves provider services.', $provider::class));
        }

        return $provider;
    }
}
