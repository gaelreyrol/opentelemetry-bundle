<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LoggerProvider;

use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;

/**
 * Returns the LoggerProvider currently registered in OpenTelemetry\API\Globals. The arguments are
 * accepted to honour the factory contract but ignored.
 */
final class GlobalsLoggerProviderFactory extends AbstractLoggerProviderFactory
{
    public function createProvider(?LogRecordProcessorInterface $processor = null, ?ResourceInfo $resource = null): LoggerProviderInterface
    {
        $provider = Globals::loggerProvider();
        if (!$provider instanceof LoggerProviderInterface) {
            throw new \LogicException(sprintf('OpenTelemetry\\API\\Globals returned a LoggerProvider of type %s, which does not implement the SDK LoggerProviderInterface. Ensure the OpenTelemetry SDK is bootstrapped (e.g. OTEL_PHP_AUTOLOAD_ENABLED=true) before this bundle resolves provider services.', $provider::class));
        }

        return $provider;
    }
}
