<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\OpenTelemetry\Context\Propagator;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceStamp;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\TraceStampPropagator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

#[CoversClass(TraceStampPropagator::class)]
class TraceStampPropagatorTest extends TestCase
{
    private TraceStampPropagator $propagator;

    protected function setUp(): void
    {
        $this->propagator = new TraceStampPropagator();
    }

    public function testSetAddsTraceStampToEnvelope(): void
    {
        $carrier = new Envelope(new \stdClass());
        $traceParent = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';

        $this->propagator->set($carrier, TraceContextPropagator::TRACEPARENT, $traceParent);

        $stamp = $carrier->last(TraceStamp::class);
        self::assertNotNull($stamp);
        self::assertSame($traceParent, $stamp->getTraceParent());
    }

    public function testSetTracestateUpdatesExistingStamp(): void
    {
        $carrier = new Envelope(new \stdClass());
        $traceParent = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';

        $this->propagator->set($carrier, TraceContextPropagator::TRACEPARENT, $traceParent);
        $this->propagator->set($carrier, TraceContextPropagator::TRACESTATE, 'vendor=value');

        $stamp = $carrier->last(TraceStamp::class);
        self::assertNotNull($stamp);
        self::assertSame($traceParent, $stamp->getTraceParent());
        self::assertSame('vendor=value', $stamp->getTraceState());
    }

    public function testSetTracestateIgnoredWithoutExistingStamp(): void
    {
        $carrier = new Envelope(new \stdClass());

        $this->propagator->set($carrier, TraceContextPropagator::TRACESTATE, 'vendor=value');

        self::assertNull($carrier->last(TraceStamp::class));
    }

    public function testSetIgnoresUnknownKey(): void
    {
        $carrier = new Envelope(new \stdClass());

        $this->propagator->set($carrier, 'baggage', 'key=value');

        self::assertNull($carrier->last(TraceStamp::class));
    }

    public function testSetThrowsOnInvalidCarrier(): void
    {
        $carrier = 'not-an-envelope';

        $this->expectException(\InvalidArgumentException::class);
        $this->propagator->set($carrier, TraceContextPropagator::TRACEPARENT, 'value');
    }

    public function testGetReturnsTraceParentFromStamp(): void
    {
        $traceParent = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';
        $carrier = new Envelope(new \stdClass(), [new TraceStamp($traceParent)]);

        $result = $this->propagator->get($carrier, TraceContextPropagator::TRACEPARENT);

        self::assertSame($traceParent, $result);
    }

    public function testGetReturnsNullWhenNoTraceStamp(): void
    {
        $carrier = new Envelope(new \stdClass());

        $result = $this->propagator->get($carrier, TraceContextPropagator::TRACEPARENT);

        self::assertNull($result);
    }

    public function testGetReturnsTraceStateFromStamp(): void
    {
        $carrier = new Envelope(new \stdClass(), [new TraceStamp('00-abc-def-01', 'vendor=value')]);

        $result = $this->propagator->get($carrier, TraceContextPropagator::TRACESTATE);

        self::assertSame('vendor=value', $result);
    }

    public function testGetReturnsNullTraceStateWhenNotSet(): void
    {
        $carrier = new Envelope(new \stdClass(), [new TraceStamp('00-abc-def-01')]);

        $result = $this->propagator->get($carrier, TraceContextPropagator::TRACESTATE);

        self::assertNull($result);
    }

    public function testGetReturnsNullForUnknownKey(): void
    {
        $traceParent = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';
        $carrier = new Envelope(new \stdClass(), [new TraceStamp($traceParent)]);

        $result = $this->propagator->get($carrier, 'baggage');

        self::assertNull($result);
    }

    public function testGetThrowsOnInvalidCarrier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->propagator->get('not-an-envelope', TraceContextPropagator::TRACEPARENT);
    }

    public function testKeysReturnsBothHeaders(): void
    {
        $carrier = new Envelope(new \stdClass());

        $keys = $this->propagator->keys($carrier);

        self::assertSame([TraceContextPropagator::TRACEPARENT, TraceContextPropagator::TRACESTATE], $keys);
    }

    public function testKeysThrowsOnInvalidCarrier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->propagator->keys('not-an-envelope');
    }
}
