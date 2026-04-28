<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\Instrumentation\Symfony\Messenger;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TraceableMessengerTransport;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\TransportTracer;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[CoversClass(TraceableMessengerTransport::class)]
#[CoversClass(TransportTracer::class)]
class TraceableMessengerTransportTest extends TestCase
{
    private InMemoryExporter $exporter;
    private TraceableMessengerTransport $transport;

    protected function setUp(): void
    {
        $this->exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->exporter));

        $innerTransport = $this->createMock(TransportInterface::class);
        $innerTransport->method('get')->willThrowException(new TransportException('Connection failed'));
        $innerTransport->method('send')->willThrowException(new TransportException('Send failed'));
        $innerTransport->method('ack')->willThrowException(new TransportException('Ack failed'));
        $innerTransport->method('reject')->willThrowException(new TransportException('Reject failed'));

        $this->transport = new TraceableMessengerTransport(
            $innerTransport,
            $tracerProvider->getTracer('test'),
        );
    }

    public function testGetRethrowsTransportExceptionAndRecordsError(): void
    {
        try {
            iterator_to_array($this->transport->get());
            self::fail('Expected TransportException was not thrown');
        } catch (TransportException $e) {
            self::assertSame('Connection failed', $e->getMessage());
        }

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame('messenger.transport.get', $spans[0]->getName());
        self::assertSame(StatusCode::STATUS_ERROR, $spans[0]->getStatus()->getCode());
        self::assertSame('Connection failed', $spans[0]->getStatus()->getDescription());
        self::assertCount(1, $spans[0]->getEvents());
        self::assertSame('exception', $spans[0]->getEvents()[0]->getName());
    }

    public function testSendRethrowsTransportExceptionAndRecordsError(): void
    {
        try {
            $this->transport->send(new Envelope(new \stdClass()));
            self::fail('Expected TransportException was not thrown');
        } catch (TransportException $e) {
            self::assertSame('Send failed', $e->getMessage());
        }

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame('messenger.transport.send', $spans[0]->getName());
        self::assertSame(StatusCode::STATUS_ERROR, $spans[0]->getStatus()->getCode());
        self::assertSame('Send failed', $spans[0]->getStatus()->getDescription());
        self::assertCount(1, $spans[0]->getEvents());
    }

    public function testAckRethrowsTransportExceptionAndRecordsError(): void
    {
        try {
            $this->transport->ack(new Envelope(new \stdClass()));
            self::fail('Expected TransportException was not thrown');
        } catch (TransportException $e) {
            self::assertSame('Ack failed', $e->getMessage());
        }

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame('messenger.transport.ack', $spans[0]->getName());
        self::assertSame(StatusCode::STATUS_ERROR, $spans[0]->getStatus()->getCode());
        self::assertSame('Ack failed', $spans[0]->getStatus()->getDescription());
        self::assertCount(1, $spans[0]->getEvents());
    }

    public function testRejectRethrowsTransportExceptionAndRecordsError(): void
    {
        try {
            $this->transport->reject(new Envelope(new \stdClass()));
            self::fail('Expected TransportException was not thrown');
        } catch (TransportException $e) {
            self::assertSame('Reject failed', $e->getMessage());
        }

        $spans = $this->exporter->getSpans();
        self::assertCount(1, $spans);
        self::assertSame('messenger.transport.reject', $spans[0]->getName());
        self::assertSame(StatusCode::STATUS_ERROR, $spans[0]->getStatus()->getCode());
        self::assertSame('Reject failed', $spans[0]->getStatus()->getDescription());
        self::assertCount(1, $spans[0]->getEvents());
    }
}
