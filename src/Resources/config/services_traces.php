<?php

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SamplerFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanExporter\AbstractSpanExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanExporter\ConsoleSpanExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanExporter\InMemorySpanExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanExporter\OtlpSpanExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanExporter\SpanExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanExporter\ZipkinSpanExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanProcessor\AbstractSpanProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanProcessor\MultiSpanProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanProcessor\NoopSpanProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanProcessor\SimpleSpanProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\TracerProvider\AbstractTracerProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\TracerProvider\DefaultTracerProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\TracerProvider\GlobalsTracerProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\TracerProvider\NoopTracerProviderFactory;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
        ->private()

        ->set('open_telemetry.traces.sampler_factory', SamplerFactory::class)
            ->factory([SamplerFactory::class, 'create'])

        // Exporters
        ->set('open_telemetry.traces.exporter_factory.abstract', AbstractSpanExporterFactory::class)
            ->abstract()
            ->args([
                service('open_telemetry.transport_factory'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.traces.exporter_factory.console', ConsoleSpanExporterFactory::class)
            ->parent('open_telemetry.traces.exporter_factory.abstract')
            ->tag('open_telemetry.traces.exporter_factory')

        ->set('open_telemetry.traces.exporter_factory.in-memory', InMemorySpanExporterFactory::class)
            ->parent('open_telemetry.traces.exporter_factory.abstract')
            ->tag('open_telemetry.traces.exporter_factory')

        ->set('open_telemetry.traces.exporter_factory.otlp', OtlpSpanExporterFactory::class)
            ->parent('open_telemetry.traces.exporter_factory.abstract')
            ->tag('open_telemetry.traces.exporter_factory')

        ->set('open_telemetry.traces.exporter_factory.zipkin', ZipkinSpanExporterFactory::class)
            ->parent('open_telemetry.traces.exporter_factory.abstract')
            ->tag('open_telemetry.traces.exporter_factory')

        ->set('open_telemetry.traces.exporter_factory', SpanExporterFactory::class)
        ->args([
            tagged_iterator('open_telemetry.traces.exporter_factory'),
        ])

        ->set('open_telemetry.traces.exporter_interface', SpanExporterInterface::class)
            ->factory([service('open_telemetry.traces.exporter_factory'), 'createExporter'])

        // Processors
        ->set('open_telemetry.traces.processor_factory.abstract', AbstractSpanProcessorFactory::class)
            ->abstract()
            ->args([
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.traces.processor_factory.multi', MultiSpanProcessorFactory::class)
            ->parent('open_telemetry.traces.processor_factory.abstract')

        ->set('open_telemetry.traces.processor_factory.noop', NoopSpanProcessorFactory::class)
            ->parent('open_telemetry.traces.processor_factory.abstract')

        ->set('open_telemetry.traces.processor_factory.simple', SimpleSpanProcessorFactory::class)
            ->parent('open_telemetry.traces.processor_factory.abstract')

        ->set('open_telemetry.traces.processor_interface', SpanProcessorInterface::class)

        // Providers
        ->set('open_telemetry.traces.provider_factory.abstract', AbstractTracerProviderFactory::class)
            ->abstract()
            ->args([
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.traces.provider_factory.noop', NoopTracerProviderFactory::class)
            ->parent('open_telemetry.traces.provider_factory.abstract')

        ->set('open_telemetry.traces.provider_factory.default', DefaultTracerProviderFactory::class)
            ->parent('open_telemetry.traces.provider_factory.abstract')

        ->set('open_telemetry.traces.provider_factory.globals', GlobalsTracerProviderFactory::class)
            ->parent('open_telemetry.traces.provider_factory.abstract')

        ->set('open_telemetry.traces.provider_interface', TracerProviderInterface::class)

        // Tracer
        ->set('open_telemetry.traces.tracer_interface', TracerInterface::class)
    ;
};
