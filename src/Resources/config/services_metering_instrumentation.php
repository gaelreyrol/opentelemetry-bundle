<?php

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Console\ObservableConsoleEventSubscriber;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\HttpKernel\ObservableHttpKernelEventSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
        ->private()

        // Console
        ->set('open_telemetry.instrumentation.console.metric.event_subscriber', ObservableConsoleEventSubscriber::class)
            ->args([tagged_iterator('open_telemetry.metrics.provider')])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        // HTTP Kernel
        ->set('open_telemetry.instrumentation.http_kernel.metric.event_subscriber', ObservableHttpKernelEventSubscriber::class)
            ->args([
                tagged_iterator('open_telemetry.metrics.provider'),
                service('open_telemetry.runtime_detector'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

    ;
};
