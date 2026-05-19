<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\HttpKernel;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Runtime\RuntimeDetector;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ObservableHttpKernelEventSubscriber implements EventSubscriberInterface
{
    private bool $shutdownRegistered = false;

    public function __construct(
        /**
         * @var iterable<MeterProviderInterface>
         */
        private readonly iterable $locator,
        private readonly RuntimeDetector $runtimeDetector,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => [
                ['flush', 10000],
            ],
        ];
    }

    public function flush(TerminateEvent $event): void
    {
        if ($this->runtimeDetector->isLongRunning()) {
            $this->registerWorkerShutdown();
            foreach ($this->locator as $provider) {
                $provider->forceFlush();
            }

            return;
        }

        foreach ($this->locator as $provider) {
            $provider->shutdown();
        }
    }

    private function registerWorkerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $providers = $this->locator;
        register_shutdown_function(static function () use ($providers): void {
            foreach ($providers as $provider) {
                $provider->shutdown();
            }
        });

        $this->shutdownRegistered = true;
    }
}
