<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\DependencyInjection;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\DependencyInjection\Configuration;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    public function testMissingRequiredServiceConfiguration(): void
    {
        $this->assertConfigurationIsInvalid([[
            'service' => [],
        ]], 'The child config "namespace" under "open_telemetry.service" must be configured');
    }

    /**
     * @param array<string, array<string,mixed>> $configs
     *
     * @return array<string, array<string,mixed>>
     */
    protected function process(array $configs): array
    {
        $processor = new Processor();

        return $processor->processConfiguration($this->getConfiguration(), $configs);
    }

    public function testEmptyConfiguration(): void
    {
        $configuration = $this->process([]);

        self::assertSame([
            'transport_http_client' => null,
            'service' => [],
            'instrumentation' => [
                'cache' => [
                    'tracing' => [
                        'enabled' => false,
                    ],
                    'metering' => [
                        'enabled' => false,
                    ],
                ],
                'console' => [
                    'type' => 'auto',
                    'tracing' => [
                        'enabled' => false,
                        'exclude_commands' => ['messenger:consume'],
                    ],
                    'metering' => [
                        'enabled' => false,
                    ],
                ],
                'doctrine' => [
                    'tracing' => [
                        'enabled' => false,
                    ],
                    'metering' => [
                        'enabled' => false,
                    ],
                ],
                'http_client' => [
                    'tracing' => [
                        'enabled' => false,
                    ],
                    'metering' => [
                        'enabled' => false,
                    ],
                ],
                'http_kernel' => [
                    'type' => 'auto',
                    'tracing' => [
                        'enabled' => false,
                        'exclude_paths' => [],
                    ],
                    'metering' => [
                        'enabled' => false,
                    ],
                ],
                'mailer' => [
                    'tracing' => [
                        'enabled' => false,
                    ],
                    'metering' => [
                        'enabled' => false,
                    ],
                ],
                'messenger' => [
                    'type' => 'auto',
                    'tracing' => [
                        'enabled' => false,
                    ],
                    'metering' => [
                        'enabled' => false,
                    ],
                ],
                'twig' => [
                    'tracing' => [
                        'enabled' => false,
                    ],
                    'metering' => [
                        'enabled' => false,
                    ],
                ],
            ],
            'traces' => [
                'tracers' => [],
                'providers' => [],
                'processors' => [],
                'exporters' => [],
            ],
            'metrics' => [
                'meters' => [],
                'providers' => [],
                'exporters' => [],
            ],
            'logs' => [
                'monolog' => [
                    'enabled' => false,
                    'handlers' => [],
                ],
                'loggers' => [],
                'providers' => [],
                'processors' => [],
                'exporters' => [],
            ],
        ], $configuration);
    }

    public function testReferenceConfiguration(): void
    {
        $dumper = new YamlReferenceDumper();

        $output = $dumper->dump(new Configuration());

        self::assertSame(<<<YML
        open_telemetry:

            # Service ID used for telemetry export transports. Must implement PSR-18 ClientInterface and PSR-17 RequestFactoryInterface, StreamFactoryInterface. Defaults to Symfony Psr18Client.
            transport_http_client: null
            service:
                namespace:            ~ # Required, Example: MyOrganization
                name:                 ~ # Required, Example: MyApp
                version:              ~ # Required, Example: 1.0.0
                environment:          ~ # Required, Example: '%kernel.environment%'
            instrumentation:
                cache:
                    tracing:
                        enabled:              false

                        # The tracer to use, defaults to `traces.default_tracer` or first tracer in `traces.tracers`
                        tracer:               ~
                    metering:
                        enabled:              false

                        # The meter to use, defaults to `metrics.default_meter` or first meter in `metrics.meters`
                        meter:                ~
                console:
                    type:                 auto # One of "auto"; "attribute"
                    tracing:
                        enabled:              false

                        # The tracer to use, defaults to `traces.default_tracer` or first tracer in `traces.tracers`
                        tracer:               ~

                        # Exclude commands from auto instrumentation
                        exclude_commands:

                            # Default:
                            - messenger:consume
                    metering:
                        enabled:              false

                        # The meter to use, defaults to `metrics.default_meter` or first meter in `metrics.meters`
                        meter:                ~
                doctrine:
                    tracing:
                        enabled:              false

                        # The tracer to use, defaults to `traces.default_tracer` or first tracer in `traces.tracers`
                        tracer:               ~
                    metering:
                        enabled:              false

                        # The meter to use, defaults to `metrics.default_meter` or first meter in `metrics.meters`
                        meter:                ~
                http_client:
                    tracing:
                        enabled:              false

                        # The tracer to use, defaults to `traces.default_tracer` or first tracer in `traces.tracers`
                        tracer:               ~
                    metering:
                        enabled:              false

                        # The meter to use, defaults to `metrics.default_meter` or first meter in `metrics.meters`
                        meter:                ~
                http_kernel:
                    type:                 auto # One of "auto"; "attribute"
                    tracing:
                        enabled:              false

                        # The tracer to use, defaults to `traces.default_tracer` or first tracer in `traces.tracers`
                        tracer:               ~

                        # Exclude paths from auto instrumentation
                        exclude_paths:        []
                    metering:
                        enabled:              false

                        # The meter to use, defaults to `metrics.default_meter` or first meter in `metrics.meters`
                        meter:                ~
                mailer:
                    tracing:
                        enabled:              false

                        # The tracer to use, defaults to `traces.default_tracer` or first tracer in `traces.tracers`
                        tracer:               ~
                    metering:
                        enabled:              false

                        # The meter to use, defaults to `metrics.default_meter` or first meter in `metrics.meters`
                        meter:                ~
                messenger:
                    type:                 auto # One of "auto"; "attribute"
                    tracing:
                        enabled:              false

                        # The tracer to use, defaults to `traces.default_tracer` or first tracer in `traces.tracers`
                        tracer:               ~
                    metering:
                        enabled:              false

                        # The meter to use, defaults to `metrics.default_meter` or first meter in `metrics.meters`
                        meter:                ~
                twig:
                    tracing:
                        enabled:              false

                        # The tracer to use, defaults to `traces.default_tracer` or first tracer in `traces.tracers`
                        tracer:               ~
                    metering:
                        enabled:              false

                        # The meter to use, defaults to `metrics.default_meter` or first meter in `metrics.meters`
                        meter:                ~
            traces:
                tracers:

                    # Prototype
                    tracer:
                        name:                 ~
                        version:              ~
                        provider:             ~ # Required
                providers:

                    # Prototype
                    provider:
                        type:                 default # One of "default"; "noop", Required
                        sampler:
                            type:                 always_on # One of "always_off"; "always_on"; "parent_based_always_off"; "parent_based_always_on"; "parent_based_trace_id_ratio"; "trace_id_ratio"; "attribute_based"; "service", Required

                            # Required if sampler type is service
                            service_id:           ~
                            options:              []
                        processors:           []
                processors:

                    # Prototype
                    processor:
                        type:                 simple # One of "multi"; "simple"; "noop", Required

                        # Required if processor type is multi
                        processors:           []

                        # Required if processor type is simple or batch
                        exporter:             ~
                exporters:

                    # Prototype
                    exporter:
                        dsn:                  ~ # Required
                        options:
                            format:               json # One of "json"; "ndjson"; "gprc"; "protobuf"
                            compression:          none # One of "gzip"; "none"
                            headers:

                                # Prototype
                                name:                 ~
                            timeout:              0.1
                            retry:                100
                            max:                  3
                            ca:                   ~
                            cert:                 ~
                            key:                  ~
            metrics:
                meters:

                    # Prototype
                    meter:
                        name:                 ~
                        provider:             ~ # Required
                providers:

                    # Prototype
                    provider:
                        type:                 default # One of "noop"; "default", Required
                        exporter:             ~
                        filter:
                            type:                 none # One of "all"; "none"; "with_sampled_trace"; "service"

                            # Required if exemplar filter type is service
                            service_id:           ~
                exporters:

                    # Prototype
                    exporter:
                        dsn:                  ~ # Required
                        temporality:          delta # One of "delta"; "cumulative"; "low_memory"
                        options:
                            format:               json # One of "json"; "ndjson"; "gprc"; "protobuf"
                            compression:          none # One of "gzip"; "none"
                            headers:

                                # Prototype
                                name:                 ~
                            timeout:              0.1
                            retry:                100
                            max:                  3
                            ca:                   ~
                            cert:                 ~
                            key:                  ~
            logs:
                monolog:
                    enabled:              false
                    handlers:

                        # Prototype
                        handler:
                            provider:             ~ # Required
                            level:                debug # One of "debug"; "info"; "notice"; "warning"; "error"; "critical"; "alert"; "emergency"
                            bubble:               true
                loggers:

                    # Prototype
                    logger:
                        name:                 ~
                        version:              ~
                        provider:             ~ # Required
                providers:

                    # Prototype
                    provider:
                        type:                 default # One of "default"; "noop", Required
                        processor:            ~
                processors:

                    # Prototype
                    processor:
                        type:                 simple # One of "batch"; "multi"; "noop"; "simple", Required

                        # Required if processor type is multi
                        processors:           []

                        # Required if processor type is batch
                        batch:
                            clock:                open_telemetry.clock
                            max_queue_size:       2048
                            schedule_delay:       1000
                            export_timeout:       30000
                            max_export_batch_size: 512
                            auto_flush:           true
                            meter_provider:       ~

                        # Required if processor type is simple or batch
                        exporter:             ~
                exporters:

                    # Prototype
                    exporter:
                        dsn:                  ~ # Required
                        options:
                            format:               json # One of "json"; "ndjson"; "gprc"; "protobuf"
                            compression:          none # One of "gzip"; "none"
                            headers:

                                # Prototype
                                name:                 ~
                            timeout:              0.1
                            retry:                100
                            max:                  3
                            ca:                   ~
                            cert:                 ~
                            key:                  ~

        YML, $output);
    }
}
