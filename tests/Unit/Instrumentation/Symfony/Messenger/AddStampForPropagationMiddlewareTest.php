<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\Instrumentation\Symfony\Messenger;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\AddStampForPropagationMiddleware;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceStamp;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Context\Propagator\TraceStampPropagator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

#[CoversClass(AddStampForPropagationMiddleware::class)]
#[CoversClass(TraceStamp::class)]
class AddStampForPropagationMiddlewareTest extends TestCase
{
    private MultiTextMapPropagator $propagator;
    private TraceStampPropagator $traceStampPropagator;

    protected function setUp(): void
    {
        while (null !== ($scope = Context::storage()->scope())) {
            $scope->detach();
        }

        $this->propagator = new MultiTextMapPropagator([TraceContextPropagator::getInstance()]);
        $this->traceStampPropagator = new TraceStampPropagator();
    }

    public function testSkipsInjectionWhenTraceStampAlreadyPresent(): void
    {
        $middleware = new AddStampForPropagationMiddleware($this->propagator, $this->traceStampPropagator);

        $originalStamp = new TraceStamp('00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01');
        $envelope = new Envelope(new \stdClass(), [$originalStamp]);

        $capturedEnvelope = null;
        $stack = $this->createStackMock($capturedEnvelope);

        $middleware->handle($envelope, $stack);

        self::assertNotNull($capturedEnvelope);
        self::assertCount(1, $capturedEnvelope->all(TraceStamp::class));
        self::assertSame($originalStamp, $capturedEnvelope->last(TraceStamp::class));
    }

    public function testDoesNotInjectWhenNoActiveScope(): void
    {
        $middleware = new AddStampForPropagationMiddleware($this->propagator, $this->traceStampPropagator);

        $envelope = new Envelope(new \stdClass());

        $capturedEnvelope = null;
        $stack = $this->createStackMock($capturedEnvelope);

        $middleware->handle($envelope, $stack);

        self::assertNotNull($capturedEnvelope);
        self::assertNull($capturedEnvelope->last(TraceStamp::class));
    }

    public function testInjectsTraceStampWhenScopeIsActive(): void
    {
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter()));
        $tracer = $tracerProvider->getTracer('test');
        $span = $tracer->spanBuilder('test')->startSpan();
        $scope = $span->activate();

        try {
            $middleware = new AddStampForPropagationMiddleware($this->propagator, $this->traceStampPropagator);

            $envelope = new Envelope(new \stdClass());

            $capturedEnvelope = null;
            $stack = $this->createStackMock($capturedEnvelope);

            $middleware->handle($envelope, $stack);

            self::assertNotNull($capturedEnvelope);

            $traceStamp = $capturedEnvelope->last(TraceStamp::class);
            self::assertNotNull($traceStamp);
            self::assertMatchesRegularExpression(
                '/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/',
                $traceStamp->getTraceParent(),
            );
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function createStackMock(?Envelope &$capturedEnvelope): StackInterface
    {
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (Envelope $envelope, StackInterface $stack) use (&$capturedEnvelope) {
                $capturedEnvelope = $envelope;

                return $envelope;
            });

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        return $stack;
    }
}
