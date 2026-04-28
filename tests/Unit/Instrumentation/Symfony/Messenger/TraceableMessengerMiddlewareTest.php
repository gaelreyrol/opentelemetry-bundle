<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\Instrumentation\Symfony\Messenger;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerMiddleware;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerStack;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

#[CoversClass(TraceableMessengerMiddleware::class)]
#[CoversClass(TraceableMessengerStack::class)]
class TraceableMessengerMiddlewareTest extends TestCase
{
    private InMemoryExporter $exporter;
    private TraceableMessengerMiddleware $middleware;

    protected function setUp(): void
    {
        while (null !== ($scope = Context::storage()->scope())) {
            $scope->detach();
        }

        $this->exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->exporter));

        $this->middleware = new TraceableMessengerMiddleware(
            $tracerProvider->getTracer('test'),
        );
    }

    protected function tearDown(): void
    {
        while (null !== ($scope = Context::storage()->scope())) {
            $scope->detach();
        }
    }

    public function testHandleCreatesSpanWithCorrectAttributes(): void
    {
        $envelope = new Envelope(new \stdClass());
        $stack = $this->createPassthroughStack();

        $this->middleware->handle($envelope, $stack);

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame('messenger.middleware', $spans[0]->getName());
        self::assertSame(SpanKind::KIND_INTERNAL, $spans[0]->getKind());
        self::assertSame('messenger.middleware', $spans[0]->getAttributes()->get('symfony.messenger.event.category'));
        self::assertSame('default', $spans[0]->getAttributes()->get('symfony.messenger.bus.name'));
        self::assertNotNull($spans[0]->getAttributes()->get('symfony.messenger.event.current'));
    }

    public function testHandleSetsStatusOkOnSuccess(): void
    {
        $envelope = new Envelope(new \stdClass());
        $stack = $this->createPassthroughStack();

        $this->middleware->handle($envelope, $stack);

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame(StatusCode::STATUS_OK, $spans[0]->getStatus()->getCode());
        self::assertCount(0, $spans[0]->getEvents());
    }

    public function testHandleSetsStatusErrorOnException(): void
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new \RuntimeException('Something went wrong');
        $stack = $this->createThrowingStack($exception);

        try {
            $this->middleware->handle($envelope, $stack);
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame(StatusCode::STATUS_ERROR, $spans[0]->getStatus()->getCode());
        self::assertSame('Something went wrong', $spans[0]->getStatus()->getDescription());
        self::assertCount(1, $spans[0]->getEvents());
        self::assertSame('exception', $spans[0]->getEvents()[0]->getName());
    }

    public function testHandleSetsStatusErrorOnError(): void
    {
        $envelope = new Envelope(new \stdClass());
        $error = new \Error('Type error');
        $stack = $this->createThrowingStack($error);

        try {
            $this->middleware->handle($envelope, $stack);
            self::fail('Expected Error was not thrown');
        } catch (\Error $caught) {
            self::assertSame($error, $caught);
        }

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame(StatusCode::STATUS_ERROR, $spans[0]->getStatus()->getCode());
        self::assertSame('Type error', $spans[0]->getStatus()->getDescription());
        self::assertCount(1, $spans[0]->getEvents());
        self::assertSame('exception', $spans[0]->getEvents()[0]->getName());
    }

    public function testStopIsIdempotent(): void
    {
        $envelope = new Envelope(new \stdClass());
        $stack = $this->createPassthroughStack();

        $this->middleware->handle($envelope, $stack);

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
    }

    public function testCustomBusNameAndEventCategory(): void
    {
        $exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($exporter));

        $middleware = new TraceableMessengerMiddleware(
            $tracerProvider->getTracer('test'),
            busName: 'command.bus',
            eventCategory: 'custom.category',
        );

        $envelope = new Envelope(new \stdClass());
        $stack = $this->createPassthroughStack();

        $middleware->handle($envelope, $stack);

        $spans = $exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame('custom.category', $spans[0]->getAttributes()->get('symfony.messenger.event.category'));
        self::assertSame('command.bus', $spans[0]->getAttributes()->get('symfony.messenger.bus.name'));
        self::assertStringContainsString('on "command.bus"', $spans[0]->getAttributes()->get('symfony.messenger.event.current'));
    }

    private function createPassthroughStack(): StackInterface
    {
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope);

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        return $stack;
    }

    private function createThrowingStack(\Throwable $throwable): StackInterface
    {
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')
            ->willThrowException($throwable);

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        return $stack;
    }
}
