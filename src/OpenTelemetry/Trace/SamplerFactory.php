<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\Sampler\AttributeBasedSampler;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\Sampler\ExpressionBasedSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;

final class SamplerFactory
{
    /**
     * @param array<int, mixed> $params
     */
    public static function create(string $name, array $params = []): SamplerInterface
    {
        $sampler = TraceSamplerEnum::tryFrom($name);

        return match ($sampler) {
            TraceSamplerEnum::AlwaysOn => new AlwaysOnSampler(),
            TraceSamplerEnum::AlwaysOff => new AlwaysOffSampler(),
            TraceSamplerEnum::ParentBasedAlwaysOn => new ParentBased(new AlwaysOnSampler()),
            TraceSamplerEnum::ParentBasedAlwaysOff => new ParentBased(new AlwaysOffSampler()),
            TraceSamplerEnum::ParentBasedTraceIdRatio => new ParentBased(new TraceIdRatioBasedSampler(...$params)),
            TraceSamplerEnum::TraceIdRatio => new TraceIdRatioBasedSampler(...$params),
            TraceSamplerEnum::AttributeBased => new AttributeBasedSampler(...$params),
            TraceSamplerEnum::ExpressionBased => new ExpressionBasedSampler(...$params),
            default => throw new \InvalidArgumentException(sprintf('Unknown sampler: %s', $name)),
        };
    }
}
