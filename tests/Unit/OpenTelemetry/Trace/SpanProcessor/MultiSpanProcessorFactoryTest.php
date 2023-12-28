<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\OpenTelemetry\Trace\SpanProcessor;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanProcessor\MultiSpanProcessorFactory;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanProcessor\NoopSpanProcessorFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace\SpanProcessor\MultiSpanProcessorFactory
 */
class MultiSpanProcessorFactoryTest extends TestCase
{
    public function testCreateProcessor(): void
    {
        MultiSpanProcessorFactory::createProcessor([NoopSpanProcessorFactory::createProcessor()]);

        self::expectExceptionObject(new \InvalidArgumentException('Processors should not be empty'));

        MultiSpanProcessorFactory::createProcessor();
    }
}
