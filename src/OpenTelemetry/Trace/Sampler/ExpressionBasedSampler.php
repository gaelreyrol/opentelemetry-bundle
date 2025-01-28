<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\Sampler;

use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ExpressionBasedSampler implements SamplerInterface
{
    public function __construct(
        private readonly string $expression,
        private readonly array $context,
        private readonly ExpressionLanguage $language,
    ) {
    }

    public function shouldSample(ContextInterface $parentContext, string $traceId, string $spanName, int $spanKind, AttributesInterface $attributes, array $links): SamplingResult
    {
        $parentSpan = Span::fromContext($parentContext);
        $parentSpanContext = $parentSpan->getContext();
        $traceState = $parentSpanContext->getTraceState();

        try {
            $result = $this->language->evaluate($this->expression, $this->context);
        } catch (\Throwable $exception) {
            return new SamplingResult(
                SamplingResult::DROP,
                [],
                $traceState,
            );
        }

        if ($result) {
            return new SamplingResult(
                SamplingResult::RECORD_AND_SAMPLE,
                [],
                $traceState
            );
        } else {
            return new SamplingResult(
                SamplingResult::RECORD_ONLY,
                [],
                $traceState
            );
        }
    }

    public function getDescription(): string
    {
        return sprintf('ExpressionBasedSampler{%s}', $this->expression);
    }
}
