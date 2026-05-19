<?php

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogExporter\AbstractLogExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogExporter\ConsoleLogExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogExporter\InMemoryLogExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogExporter\LogExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogExporter\NoopLogExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogExporter\OtlpLogExporterFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LoggerProvider\AbstractLoggerProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LoggerProvider\DefaultLoggerProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LoggerProvider\GlobalsLoggerProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LoggerProvider\NoopLoggerProviderFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogProcessor\AbstractLogProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogProcessor\BatchLogProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogProcessor\MultiLogProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogProcessor\NoopLogProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LogProcessor\SimpleLogProcessorFactory;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Common\Time\SystemClock;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
        ->private()

        // Exporters
        ->set('open_telemetry.logs.exporter_factory.abstract', AbstractLogExporterFactory::class)
            ->abstract()
            ->args([
                service('open_telemetry.transport_factory'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.logs.exporter_factory.console', ConsoleLogExporterFactory::class)
            ->parent('open_telemetry.logs.exporter_factory.abstract')
            ->tag('open_telemetry.logs.exporter_factory')

        ->set('open_telemetry.logs.exporter_factory.in-memory', InMemoryLogExporterFactory::class)
            ->parent('open_telemetry.logs.exporter_factory.abstract')
            ->tag('open_telemetry.logs.exporter_factory')

        ->set('open_telemetry.logs.exporter_factory.noop', NoopLogExporterFactory::class)
            ->parent('open_telemetry.logs.exporter_factory.abstract')
            ->tag('open_telemetry.logs.exporter_factory')

        ->set('open_telemetry.logs.exporter_factory.otlp', OtlpLogExporterFactory::class)
            ->parent('open_telemetry.logs.exporter_factory.abstract')
            ->tag('open_telemetry.logs.exporter_factory')

        ->set('open_telemetry.logs.exporter_factory', LogExporterFactory::class)
            ->args([
                tagged_iterator('open_telemetry.logs.exporter_factory'),
            ])

        ->set('open_telemetry.logs.exporter_interface', LogRecordExporterInterface::class)
            ->factory([service('open_telemetry.logs.exporter_factory'), 'createExporter'])

        // Processors
        ->set('open_telemetry.logs.processor_factory.abstract', AbstractLogProcessorFactory::class)
            ->abstract()
            ->args([
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.clock', ClockInterface::class)
            ->factory([SystemClock::class, 'create'])

        ->set('open_telemetry.logs.processor_factory.batch', BatchLogProcessorFactory::class)
            ->parent('open_telemetry.logs.processor_factory.abstract')

        ->set('open_telemetry.logs.processor_factory.multi', MultiLogProcessorFactory::class)
            ->parent('open_telemetry.logs.processor_factory.abstract')

        ->set('open_telemetry.logs.processor_factory.noop', NoopLogProcessorFactory::class)
            ->parent('open_telemetry.logs.processor_factory.abstract')

        ->set('open_telemetry.logs.processor_factory.simple', SimpleLogProcessorFactory::class)
            ->parent('open_telemetry.logs.processor_factory.abstract')

        ->set('open_telemetry.logs.processor_interface', LogRecordProcessorInterface::class)

        // Providers
        ->set('open_telemetry.logs.provider_factory.abstract', AbstractLoggerProviderFactory::class)
            ->abstract()
            ->args([
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.logs.provider_factory.noop', NoopLoggerProviderFactory::class)
            ->parent('open_telemetry.logs.provider_factory.abstract')

        ->set('open_telemetry.logs.provider_factory.default', DefaultLoggerProviderFactory::class)
            ->parent('open_telemetry.logs.provider_factory.abstract')

        ->set('open_telemetry.logs.provider_factory.globals', GlobalsLoggerProviderFactory::class)
            ->parent('open_telemetry.logs.provider_factory.abstract')

        ->set('open_telemetry.logs.provider_interface', LoggerProviderInterface::class)

        // Logger
        ->set('open_telemetry.logs.logger_interface', LoggerInterface::class)
    ;
};
