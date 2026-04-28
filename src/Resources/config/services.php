<?php

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\HeadersPropagator as HeadersPropagationGetter;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\PropagatorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Exporter\ExporterDsn;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Exporter\OtlpExporterOptions;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Resource\ResourceInfoFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetryBundle;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use OpenTelemetry\Context\Propagation\SanitizeCombinedHeadersPropagationGetter;
use OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator;
use OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('open_telemetry.bundle.name', OpenTelemetryBundle::name())
        ->set('open_telemetry.bundle.version', OpenTelemetryBundle::version())
    ;

    $container->services()
        ->defaults()
        ->private()

        ->set('open_telemetry.resource_info', ResourceInfo::class)
            ->factory([ResourceInfoFactory::class, 'create'])

        ->set('open_telemetry.propagator.server_timing', ServerTimingPropagator::class)
        ->set('open_telemetry.propagator.trace_response', TraceResponsePropagator::class)

        ->set('open_telemetry.propagator_text_map.noop', NoopTextMapPropagator::class)
        ->set('open_telemetry.propagator_text_map.multi', MultiTextMapPropagator::class)
            ->factory([PropagatorFactory::class, 'createDefault'])

        ->set('open_telemetry.propagation_getter.headers', HeadersPropagationGetter::class)
        ->set('open_telemetry.propagation_getter.sanitize_combined_headers', SanitizeCombinedHeadersPropagationGetter::class)

        ->set('open_telemetry.propagation_getter_setter.array_access', ArrayAccessGetterSetter::class)

        ->set('open_telemetry.exporter_dsn', ExporterDsn::class)
            ->factory([ExporterDsn::class, 'fromString'])

        ->set('open_telemetry.otlp_exporter_options', OtlpExporterOptions::class)
            ->factory([OtlpExporterOptions::class, 'fromConfiguration'])
    ;
};
