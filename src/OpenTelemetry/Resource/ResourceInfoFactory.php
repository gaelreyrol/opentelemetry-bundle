<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Resource;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\DeploymentIncubatingAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\ProcessIncubatingAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\ServiceIncubatingAttributes;
use OpenTelemetry\SemConv\Version;

final class ResourceInfoFactory
{
    /**
     * process.pid is included so multiple long-running workers (FrankenPHP, Swoole, RoadRunner...)
     * produce distinct time series instead of collapsing into one under the same service.* identity.
     * It is harmless under shared-nothing FPM since the PID changes per process anyway.
     */
    public static function create(string $namespace, string $name, string $version, string $environment): ResourceInfo
    {
        $resourceInfo = \OpenTelemetry\SDK\Resource\ResourceInfoFactory::defaultResource();

        return $resourceInfo->merge(ResourceInfo::create(Attributes::create([
            ServiceIncubatingAttributes::SERVICE_NAMESPACE => $namespace,
            ServiceAttributes::SERVICE_NAME => $name,
            ServiceAttributes::SERVICE_VERSION => $version,
            DeploymentIncubatingAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $environment,
            ProcessIncubatingAttributes::PROCESS_PID => getmypid(),
        ]), Version::VERSION_1_38_0->url()));
    }
}
