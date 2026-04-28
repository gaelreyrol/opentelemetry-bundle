<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\InstrumentationTypeEnum;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * @phpstan-type InstrumentationConfig array{
 *     type?: string,
 *     tracing: TracingInstrumentationConfig,
 *     metering: MeteringInstrumentationConfig,
 * }
 * @phpstan-type TracingInstrumentationConfig array{
 *     enabled: bool,
 *     tracer: ?string,
 *     exclude_paths?: string[],
 *     exclude_commands?: string[]
 * }
 * @phpstan-type MeteringInstrumentationConfig array{
 *     enabled: bool,
 *     meter: ?string,
 * }
 */
final class OpenTelemetryExtension extends ConfigurableExtension
{
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $loader->load('services.php');

        $channels = $container->hasParameter('monolog.additional_channels')
            ? $container->getParameter('monolog.additional_channels')
            : [];
        $channels[] = 'open_telemetry';
        $container->setParameter('monolog.additional_channels', array_unique($channels));

        $loader->load('services_transports.php');
        $loader->load('services_logs.php');
        $loader->load('services_metrics.php');
        $loader->load('services_traces.php');
        $loader->load('services_tracing_instrumentation.php');
        $loader->load('services_metering_instrumentation.php');

        $this->registerTransportHttpClient($mergedConfig['transport_http_client'], $container);
        $this->registerService($mergedConfig['service'], $container);
        $this->registerInstrumentation($mergedConfig['instrumentation'], $container);

        (new OpenTelemetryTracesExtension())($mergedConfig['traces'], $container);
        (new OpenTelemetryMetricsExtension())($mergedConfig['metrics'], $container);
        (new OpenTelemetryLogsExtension())($mergedConfig['logs'], $container);
    }

    /**
     * @param array{
     *     namespace: string,
     *     name: string,
     *     version: string,
     *     environment: string
     * } $config
     */
    private function registerService(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('open_telemetry.service.namespace', $config['namespace']);
        $container->setParameter('open_telemetry.service.name', $config['name']);
        $container->setParameter('open_telemetry.service.version', $config['version']);
        $container->setParameter('open_telemetry.service.environment', $config['environment']);

        $container->getDefinition('open_telemetry.resource_info')
            ->setArguments([
                $config['namespace'],
                $config['name'],
                $config['version'],
                $config['environment'],
            ]);
    }

    private function registerTransportHttpClient(?string $httpClientServiceId, ContainerBuilder $container): void
    {
        if (null !== $httpClientServiceId) {
            $container->setAlias('open_telemetry.transport_http_client', $httpClientServiceId);

            return;
        }

        if (class_exists(Psr18Client::class)) {
            $container->register('open_telemetry.transport_http_client.psr18', Psr18Client::class);
            $container->setAlias('open_telemetry.transport_http_client', 'open_telemetry.transport_http_client.psr18');
        }
    }

    /**
     * @param array{
     *     cache: InstrumentationConfig,
     *     console: InstrumentationConfig,
     *     doctrine: InstrumentationConfig,
     *     http_client: InstrumentationConfig,
     *     http_kernel: InstrumentationConfig,
     *     mailer: InstrumentationConfig,
     *     messenger: InstrumentationConfig,
     *     twig: InstrumentationConfig,
     * } $config
     */
    private function registerInstrumentation(array $config, ContainerBuilder $container): void
    {
        $this->registerCacheTracingInstrumentationConfiguration($container, $config['cache']);

        $this->registerConsoleTracingInstrumentationConfiguration($container, $config['console']);
        $this->registerConsoleMeteringInstrumentationConfiguration($container, $config['console']);

        $this->registerDoctrineTracingInstrumentationConfiguration($container, $config['doctrine']);
        $this->registerHttpClientTracingInstrumentationConfiguration($container, $config['http_client']);

        $this->registerHttpKernelTracingInstrumentationConfiguration($container, $config['http_kernel']);
        $this->registerHttpKernelMeteringInstrumentationConfiguration($container, $config['http_kernel']);

        $this->registerMailerTracingInstrumentationConfiguration($container, $config['mailer']);
        $this->registerMessengerTracingInstrumentationConfiguration($container, $config['messenger']);
        $this->registerTwigTracingInstrumentationConfiguration($container, $config['twig']);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerCacheTracingInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['tracing']);

        if ($isConfigEnabled && !class_exists(CacheItem::class)) {
            throw new \LogicException('Cache instrumentation cannot be enabled because the symfony/cache package is not installed.');
        }

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.cache.trace.adapter');
            $container->removeDefinition('open_telemetry.instrumentation.cache.trace.tag_aware_adapter');
        }

        $this->setTracingInstrumentationParams($container, 'cache', $config, $isConfigEnabled);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerConsoleTracingInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['tracing']);

        if ($isConfigEnabled && !class_exists(Command::class)) {
            throw new \LogicException('Console instrumentation cannot be enabled because the symfony/console package is not installed.');
        }

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.console.trace.event_subscriber');
        }

        $this->setTracingInstrumentationParams($container, 'console', $config, $isConfigEnabled);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerConsoleMeteringInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['metering']);

        if ($isConfigEnabled && !class_exists(Command::class)) {
            throw new \LogicException('Console instrumentation cannot be enabled because the symfony/console package is not installed.');
        }

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.console.metric.event_subscriber');
        }
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerDoctrineTracingInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['tracing']);

        if ($isConfigEnabled && !class_exists(DoctrineBundle::class)) {
            throw new \LogicException('DBAL tracing support cannot be enabled because the doctrine/doctrine-bundle Composer package is not installed.');
        }

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.doctrine.trace.middleware');
        }

        $this->setTracingInstrumentationParams($container, 'doctrine', $config, $isConfigEnabled);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerHttpClientTracingInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['tracing']);

        if ($isConfigEnabled && !class_exists(HttpClient::class)) {
            throw new \LogicException('Http client tracing support cannot be enabled because the symfony/http-client Composer package is not installed.');
        }

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.http_client.trace.client');
        }

        $this->setTracingInstrumentationParams($container, 'http_client', $config, $isConfigEnabled);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerHttpKernelTracingInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['tracing']);

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.http_kernel.trace.event_subscriber');
            $container->removeDefinition('open_telemetry.instrumentation.http_kernel.trace.route_loader');
        }

        $this->setTracingInstrumentationParams($container, 'http_kernel', $config, $isConfigEnabled);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerHttpKernelMeteringInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['metering']);

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.http_kernel.metric.event_subscriber');
        }
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerMailerTracingInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['tracing']);

        if ($isConfigEnabled && !interface_exists(MailerInterface::class)) {
            throw new \LogicException('Mailer instrumentation cannot be enabled because the symfony/mailer package is not installed.');
        }

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.mailer.trace.transports');
            $container->removeDefinition('open_telemetry.instrumentation.mailer.trace.default_transport');
            $container->removeDefinition('open_telemetry.instrumentation.mailer.trace.mailer');
        }

        $this->setTracingInstrumentationParams($container, 'mailer', $config, $isConfigEnabled);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerMessengerTracingInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['tracing']);

        if ($isConfigEnabled && !class_exists(Envelope::class)) {
            throw new \LogicException('Messenger instrumentation cannot be enabled because the symfony/messenger package is not installed.');
        }

        if (!$isConfigEnabled) {
            $container->removeAlias('messenger.transport.open_telemetry_tracer.factory');
            $container->removeAlias('messenger.middleware.open_telemetry_tracer');
            $container->removeDefinition('open_telemetry.instrumentation.messenger.trace.transport');
            $container->removeDefinition('open_telemetry.instrumentation.messenger.trace.transport_factory');
            $container->removeDefinition('open_telemetry.instrumentation.messenger.trace.middleware');
            $container->removeDefinition('open_telemetry.instrumentation.messenger.trace_stamp_propagator');
            $container->removeDefinition('open_telemetry.instrumentation.messenger.propagation.middleware');
            $container->removeDefinition('open_telemetry.instrumentation.messenger.worker');
        }

        $this->setTracingInstrumentationParams($container, 'messenger', $config, $isConfigEnabled);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function registerTwigTracingInstrumentationConfiguration(ContainerBuilder $container, array $config): void
    {
        $isConfigEnabled = $this->isConfigEnabled($container, $config['tracing']);

        if ($isConfigEnabled && !class_exists(TwigBundle::class)) {
            throw new \LogicException('Twig instrumentation cannot be enabled because the symfony/twig-bundle package is not installed.');
        }

        if (!$isConfigEnabled) {
            $container->removeDefinition('open_telemetry.instrumentation.twig.trace.extension');
        }

        $this->setTracingInstrumentationParams($container, 'twig', $config, $isConfigEnabled);
    }

    /**
     * @param InstrumentationConfig $config
     */
    private function setTracingInstrumentationParams(ContainerBuilder $container, string $name, array $config, bool $enabled): void
    {
        $container->setParameter(sprintf('open_telemetry.instrumentation.%s.tracing.enabled', $name), $enabled);
        if (isset($config['type'])) {
            $container->setParameter(
                sprintf('open_telemetry.instrumentation.%s.type', $name),
                InstrumentationTypeEnum::from($config['type']),
            );
        }

        if ('http_kernel' === $name) {
            if (isset($config['tracing']['exclude_paths'])
                && 0 < \count($config['tracing']['exclude_paths'])
            ) {
                $container->setParameter(
                    sprintf('open_telemetry.instrumentation.%s.tracing.exclude_paths', $name),
                    $config['tracing']['exclude_paths'],
                );
            }
        }

        if ('console' === $name) {
            if (isset($config['tracing']['exclude_commands'])
                && 0 < \count($config['tracing']['exclude_commands'])
            ) {
                $container->setParameter(
                    sprintf('open_telemetry.instrumentation.%s.tracing.exclude_commands', $name),
                    $config['tracing']['exclude_commands'],
                );
            }
        }

        $container->setParameter(
            sprintf('open_telemetry.instrumentation.%s.tracing.tracer', $name),
            $config['tracing']['tracer'] ?? 'default_tracer',
        );
    }
}
