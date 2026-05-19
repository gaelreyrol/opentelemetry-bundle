<?php

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\ExemplarFilterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MeterProvider\AbstractMeterProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MeterProvider\DefaultMeterProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MeterProvider\GlobalsMeterProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MeterProvider\NoopMeterProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MetricExporter\AbstractMetricExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MetricExporter\ConsoleMetricExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MetricExporter\InMemoryMetricExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MetricExporter\MetricExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MetricExporter\NoopMetricExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MetricExporter\OtlpMetricExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MetricExporterOptions;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
        ->private()

        ->set('open_telemetry.metric_exporter_options', MetricExporterOptions::class)
            ->factory([MetricExporterOptions::class, 'fromConfiguration'])

        // Exemplar Filters
        ->set('open_telemetry.metrics.exemplar_filter_factory', ExemplarFilterFactory::class)
            ->factory([ExemplarFilterFactory::class, 'create'])

        // Exporters
        ->set('open_telemetry.metrics.exporter_factory.abstract', AbstractMetricExporterFactory::class)
            ->abstract()
            ->args([
                service('open_telemetry.transport_factory'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.metrics.exporter_factory.console', ConsoleMetricExporterFactory::class)
            ->parent('open_telemetry.metrics.exporter_factory.abstract')
            ->tag('open_telemetry.metrics.exporter_factory')

        ->set('open_telemetry.metrics.exporter_factory.in-memory', InMemoryMetricExporterFactory::class)
            ->parent('open_telemetry.metrics.exporter_factory.abstract')
            ->tag('open_telemetry.metrics.exporter_factory')

        ->set('open_telemetry.metrics.exporter_factory.noop', NoopMetricExporterFactory::class)
            ->parent('open_telemetry.metrics.exporter_factory.abstract')
            ->tag('open_telemetry.metrics.exporter_factory')

        ->set('open_telemetry.metrics.exporter_factory.otlp', OtlpMetricExporterFactory::class)
            ->parent('open_telemetry.metrics.exporter_factory.abstract')
            ->tag('open_telemetry.metrics.exporter_factory')

        ->set('open_telemetry.metrics.exporter_factory', MetricExporterFactory::class)
            ->args([
                tagged_iterator('open_telemetry.metrics.exporter_factory'),
            ])

        ->set('open_telemetry.metrics.exporter_interface', MetricExporterInterface::class)
            ->factory([service('open_telemetry.metrics.exporter_factory'), 'createExporter'])

        // Providers
        ->set('open_telemetry.metrics.provider_factory.abstract', AbstractMeterProviderFactory::class)
            ->abstract()
            ->args([
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.metrics.provider_factory.noop', NoopMeterProviderFactory::class)
            ->parent('open_telemetry.metrics.provider_factory.abstract')

        ->set('open_telemetry.metrics.provider_factory.default', DefaultMeterProviderFactory::class)
            ->parent('open_telemetry.metrics.provider_factory.abstract')

        ->set('open_telemetry.metrics.provider_factory.globals', GlobalsMeterProviderFactory::class)
            ->parent('open_telemetry.metrics.provider_factory.abstract')

        ->set('open_telemetry.metrics.provider_interface', MeterProviderInterface::class)

        // Meter
        ->set('open_telemetry.metrics.meter_interface', MeterInterface::class)
    ;
};
