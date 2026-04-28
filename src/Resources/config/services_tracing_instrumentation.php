<?php

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Doctrine\Middleware\TraceableMiddleware as TraceableDoctrineMiddleware;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Cache\TagAwareTraceableCacheAdapter;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Cache\TraceableCacheAdapter;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Console\TraceableConsoleEventSubscriber;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Framework\Routing\TraceableRouteLoader;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\HttpClient\TraceableHttpClient;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\HttpKernel\TraceableHttpKernelEventSubscriber;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Mailer\TraceableMailer;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Mailer\TraceableMailerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\AddStampForPropagationMiddleware;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerMiddleware;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransportFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\WorkerMessageEventSubscriber;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Twig\TraceableTwigExtension;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\TraceStampPropagator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
        ->private()

        // Cache
        ->set('open_telemetry.instrumentation.cache.trace.adapter', TraceableCacheAdapter::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->abstract()
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.instrumentation.cache.trace.tag_aware_adapter', TagAwareTraceableCacheAdapter::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->abstract()
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        // Console
        ->set('open_telemetry.instrumentation.console.trace.event_subscriber', TraceableConsoleEventSubscriber::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->tag('kernel.event_subscriber')
            ->tag('container.service_subscriber')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        // Doctrine
        ->set('open_telemetry.instrumentation.doctrine.trace.middleware', TraceableDoctrineMiddleware::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->tag('doctrine.middleware')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        // HTTP Client
        ->set('open_telemetry.instrumentation.http_client.trace.client', TraceableHttpClient::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->arg('$uriFactory', service('open_telemetry.transport_http_client'))
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        // HTTP Kernel
        ->set('open_telemetry.instrumentation.http_kernel.trace.event_subscriber', TraceableHttpKernelEventSubscriber::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->arg('$propagator', service('open_telemetry.propagator_text_map.noop'))
            ->arg('$propagationGetter', service('open_telemetry.propagation_getter.headers'))
            ->tag('kernel.event_subscriber')
            ->tag('container.service_subscriber')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.instrumentation.http_kernel.trace.route_loader', TraceableRouteLoader::class)
            ->decorate('routing.loader')
            ->arg('$loader', service('.inner'))
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        // Mailer
        ->set('open_telemetry.instrumentation.mailer.trace.transports', TraceableMailerTransport::class)
            ->decorate('mailer.transports')
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->arg('$transport', service('.inner'))
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.instrumentation.mailer.trace.default_transport', TraceableMailerTransport::class)
            ->decorate('mailer.default_transport')
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->arg('$transport', service('.inner'))
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.instrumentation.mailer.trace.mailer', TraceableMailer::class)
            ->decorate('mailer.mailer')
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->arg('$mailer', service('.inner'))
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        // Messenger
        ->set('open_telemetry.instrumentation.messenger.trace.transport', TraceableMessengerTransport::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.instrumentation.messenger.trace.transport_factory', TraceableMessengerTransportFactory::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->arg('$transportFactory', service('messenger.transport_factory'))
            ->tag('messenger.transport_factory')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])
        ->alias('messenger.transport.open_telemetry_tracer.factory', 'open_telemetry.instrumentation.messenger.trace.transport_factory')

        ->set('open_telemetry.instrumentation.messenger.trace.middleware', TraceableMessengerMiddleware::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->tag('messenger.middleware')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])
        ->alias('messenger.middleware.open_telemetry_tracer', 'open_telemetry.instrumentation.messenger.trace.middleware')

        ->set('open_telemetry.instrumentation.messenger.trace_stamp_propagator', TraceStampPropagator::class)
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.instrumentation.messenger.propagation.middleware', AddStampForPropagationMiddleware::class)
            ->arg('$propagator', service('open_telemetry.propagator_text_map.multi'))
            ->arg('$traceStampPropagator', service('open_telemetry.instrumentation.messenger.trace_stamp_propagator'))
            ->tag('messenger.middleware')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        ->set('open_telemetry.instrumentation.messenger.worker', WorkerMessageEventSubscriber::class)
            ->arg('$propagator', service('open_telemetry.propagator_text_map.multi'))
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->arg('$traceStampPropagator', service('open_telemetry.instrumentation.messenger.trace_stamp_propagator'))
            ->tag('kernel.event_subscriber')
            ->tag('container.service_subscriber')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])

        // Twig
        ->set('open_telemetry.instrumentation.twig.trace.extension', TraceableTwigExtension::class)
            ->arg('$tracer', service('open_telemetry.traces.default_tracer'))
            ->tag('twig.extension')
            ->tag('monolog.logger', ['channel' => 'open_telemetry'])
    ;
};
