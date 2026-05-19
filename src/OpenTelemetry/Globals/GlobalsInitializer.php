<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Globals;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\ProviderSource;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Publishes the bundle's DI-built providers into OpenTelemetry\API\Globals so third-party libraries
 * and auto-instrumentation packages that reach for Globals::tracerProvider() / meterProvider() /
 * loggerProvider() see the same instances the bundle uses.
 *
 * The registration is performed once, on the first kernel.request with the highest priority, so
 * any code in the request path (or beyond) sees consistent providers. The Globals SDK invokes the
 * initializer lazily on first read, so no provider is instantiated eagerly here.
 *
 * When provider_source is `globals` this initializer is a no-op — the SDK is bootstrapped externally
 * and the bundle's services consume Globals rather than publish into them.
 *
 * Only the first tagged provider per signal is published. Bundles configuring multiple providers
 * per signal can override the choice by adding `priority` to the tags.
 */
final class GlobalsInitializer implements EventSubscriberInterface
{
    private bool $registered = false;

    /**
     * @param iterable<TracerProviderInterface> $tracerProviders
     * @param iterable<MeterProviderInterface>  $meterProviders
     * @param iterable<LoggerProviderInterface> $loggerProviders
     */
    public function __construct(
        private readonly iterable $tracerProviders,
        private readonly iterable $meterProviders,
        private readonly iterable $loggerProviders,
        private readonly string $providerSource,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['register', 99999]],
        ];
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        if (ProviderSource::Di->value !== $this->providerSource) {
            return;
        }

        $tracerProvider = self::first($this->tracerProviders);
        $meterProvider = self::first($this->meterProviders);
        $loggerProvider = self::first($this->loggerProviders);

        if (null === $tracerProvider && null === $meterProvider && null === $loggerProvider) {
            return;
        }

        Globals::registerInitializer(static function (Configurator $configurator) use ($tracerProvider, $meterProvider, $loggerProvider): Configurator {
            if ($tracerProvider instanceof TracerProviderInterface) {
                $configurator = $configurator->withTracerProvider($tracerProvider);
            }
            if ($meterProvider instanceof MeterProviderInterface) {
                $configurator = $configurator->withMeterProvider($meterProvider);
            }
            if ($loggerProvider instanceof LoggerProviderInterface) {
                $configurator = $configurator->withLoggerProvider($loggerProvider);
            }

            return $configurator;
        });
    }

    /**
     * @template T of object
     *
     * @param iterable<T> $iter
     *
     * @return T|null
     */
    private static function first(iterable $iter): ?object
    {
        foreach ($iter as $item) {
            return $item;
        }

        return null;
    }
}
