<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\OpenTelemetry\Context\Propagator;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\PropagatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PropagatorFactory::class)]
class PropagatorFactoryTest extends TestCase
{
    public function testCreateDefaultIncludesTraceContextAndBaggageFields(): void
    {
        $propagator = PropagatorFactory::createDefault();

        $fields = $propagator->fields();

        self::assertContains('traceparent', $fields);
        self::assertContains('tracestate', $fields);
        self::assertContains('baggage', $fields);
    }
}
