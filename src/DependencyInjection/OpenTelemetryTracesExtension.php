<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\DependencyInjection;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Exporter\ExporterOptionsInterface;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\TraceSamplerEnum;
use OpenTelemetry\API\Trace\TracerInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @phpstan-import-type ExporterOptions from ExporterOptionsInterface
 */
final class OpenTelemetryTracesExtension
{
    /**
     * @var array<string, mixed>
     */
    private array $config;
    private ContainerBuilder $container;

    /**
     * @param array{
     *     default_tracer?: string,
     *     tracers: array<string, mixed>,
     *     exporters: array<string, mixed>,
     *     processors: array<string, mixed>,
     *     providers: array<string, mixed>
     * }|array<string, mixed> $config
     */
    public function __invoke(array $config, ContainerBuilder $container): void
    {
        $this->config = $config;
        $this->container = $container;

        foreach ($this->config['exporters'] as $name => $exporter) {
            $this->loadTraceExporter($name, $exporter);
        }

        foreach ($this->config['processors'] as $name => $processor) {
            $this->loadTraceProcessor($name, $processor);
        }

        foreach ($this->config['providers'] as $name => $provider) {
            $this->loadTraceProvider($name, $provider);
        }

        foreach ($this->config['tracers'] as $name => $tracer) {
            $this->loadTraceTracer($name, $tracer);
        }

        $defaultTracer = null;
        if (0 < count($this->config['tracers'])) {
            $defaultTracer = array_key_first($this->config['tracers']);
        }

        if (null !== $defaultTracer) {
            $this->container->setAlias('open_telemetry.traces.default_tracer', new Reference(sprintf('open_telemetry.traces.tracers.%s', $defaultTracer)));
            $this->container->setAlias(TracerInterface::class, new Reference(sprintf('open_telemetry.traces.tracers.%s', $defaultTracer)));
        }
    }

    /**
     * @param array{
     *      dsn: string,
     *      options?: ExporterOptions
     *  } $config
     */
    private function loadTraceExporter(string $name, array $config): void
    {
        $dsn = (new ChildDefinition('open_telemetry.exporter_dsn'))->setArguments([$config['dsn']]);
        $exporterOptions = (new ChildDefinition('open_telemetry.otlp_exporter_options'))->setArguments([$config['options'] ?? []]);

        $this->container
            ->setDefinition(
                sprintf('open_telemetry.traces.exporters.%s', $name),
                new ChildDefinition('open_telemetry.traces.exporter_interface')
            )
            ->setArguments([$dsn, $exporterOptions]);
    }

    /**
     * @param array{
     *      type: string,
     *      processors?: string[],
     *      exporter?: string
     *  } $config
     */
    private function loadTraceProcessor(string $name, array $config): void
    {
        $this->container
            ->setDefinition(
                sprintf('open_telemetry.traces.processors.%s', $name),
                new ChildDefinition('open_telemetry.traces.processor_interface'),
            )
            ->setFactory([new Reference(sprintf('open_telemetry.traces.processor_factory.%s', $config['type'])), 'createProcessor'])
            ->setArguments([
                isset($config['processors']) ? array_map(fn (string $processor) => new Reference($processor), $config['processors']) : [],
                isset($config['exporter']) ? new Reference($config['exporter']) : null,
            ]);
    }

    /**
     * @param array{
     *     type: string,
     *     sampler?: array{type: string, service_id?: string, options?: array<int, mixed>},
     *     processors?: string[]
     * } $config
     */
    private function loadTraceProvider(string $name, array $config): void
    {
        $sampler = (new ChildDefinition('open_telemetry.traces.sampler_factory'));

        $params = $config['sampler']['options'] ?? [];
        if (isset($config['sampler']['type']) && TraceSamplerEnum::Service->value === $config['sampler']['type']) {
            if (!array_key_exists('service_id', $config['sampler'])) {
                throw new \LogicException('To configure a sampler of type service, you must specify the service_id key');
            }
            $params['service_id'] = new Reference($config['sampler']['service_id']);
        }
        $sampler->setArguments([
            $config['sampler']['type'] ?? TraceSamplerEnum::AlwaysOn->value,
            $params,
        ]);

        $this->container
            ->setDefinition(
                sprintf('open_telemetry.traces.providers.%s', $name),
                new ChildDefinition('open_telemetry.traces.provider_interface'),
            )
            ->setFactory([new Reference(sprintf('open_telemetry.traces.provider_factory.%s', $config['type'])), 'createProvider'])
            ->setArguments([
                $sampler,
                isset($config['processors']) ? array_map(fn (string $processor) => new Reference($processor), $config['processors']) : null,
                new Reference('open_telemetry.resource_info'),
            ])
            ->addTag('open_telemetry.traces.provider');
    }

    /**
     * @param array{
     *     name?: string,
     *     version?: string,
     *     provider: string
     * } $tracer
     */
    private function loadTraceTracer(string $name, array $tracer): void
    {
        $this->container
            ->setDefinition(
                sprintf('open_telemetry.traces.tracers.%s', $name),
                new ChildDefinition('open_telemetry.traces.tracer_interface'),
            )
            ->setPublic(true)
            ->setFactory([new Reference($tracer['provider']), 'getTracer'])
            ->setArguments([
                $tracer['name'] ?? $this->container->getParameter('open_telemetry.bundle.name'),
                $tracer['version'] ?? $this->container->getParameter('open_telemetry.bundle.version'),
            ])
            ->addTag('open_telemetry.tracer');
    }
}
