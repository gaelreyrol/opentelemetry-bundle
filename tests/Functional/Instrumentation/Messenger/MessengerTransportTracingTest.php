<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Instrumentation\Messenger;

use App\Kernel;
use App\Message\DummyMessage;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\TracingTestCaseTrait;
use OpenTelemetry\SDK\Trace\StatusData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
class MessengerTransportTracingTest extends KernelTestCase
{
    use TracingTestCaseTrait;

    private TransportInterface $transport;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->transport = self::getContainer()->get('messenger.transport.main');
    }

    public function testGetCreatesSpan(): void
    {
        $this->transport->send(new Envelope(new DummyMessage('test')));
        iterator_to_array($this->transport->get());

        self::assertSpansCount(2);

        $sendSpan = self::getSpans()[0];
        self::assertSpanName($sendSpan, 'messenger.transport.send');
        self::assertSpanStatus($sendSpan, StatusData::unset());

        $getSpan = self::getSpans()[1];
        self::assertSpanName($getSpan, 'messenger.transport.get');
        self::assertSpanStatus($getSpan, StatusData::unset());
    }

    public function testAckCreatesSpan(): void
    {
        $this->transport->send(new Envelope(new DummyMessage('test')));
        $envelopes = iterator_to_array($this->transport->get());

        $this->transport->ack($envelopes[0]);

        self::assertSpansCount(3);

        self::assertSpanName(self::getSpans()[2], 'messenger.transport.ack');
        self::assertSpanStatus(self::getSpans()[2], StatusData::unset());
    }

    public function testRejectCreatesSpan(): void
    {
        $this->transport->send(new Envelope(new DummyMessage('test')));
        $envelopes = iterator_to_array($this->transport->get());

        $this->transport->reject($envelopes[0]);

        self::assertSpansCount(3);

        self::assertSpanName(self::getSpans()[2], 'messenger.transport.reject');
        self::assertSpanStatus(self::getSpans()[2], StatusData::unset());
    }
}
