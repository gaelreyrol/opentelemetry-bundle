<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Instrumentation;

use App\Kernel;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\TracingTestCaseTrait;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\StatusData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
class MailerTracingTest extends KernelTestCase
{
    use TracingTestCaseTrait;

    public function testSend(): void
    {
        $kernel = self::bootKernel();

        $mailer = $kernel->getContainer()->get('mailer.mailer');

        $email = (new Email())
            ->from('hello@example.com')
            ->to('you@example.com')
            ->subject('Mailer test')
            ->text('A text body')
            ->html('<p>An html body</p>');

        $mailer->send($email);

        self::assertEmailCount(1);
        self::assertSpansCount(5);

        $sendMiddlewareSpan = self::getSpans()[0];
        self::assertSpanName($sendMiddlewareSpan, 'messenger.middleware');
        self::assertSpanStatus($sendMiddlewareSpan, StatusData::ok());
        self::assertSpanAttributes($sendMiddlewareSpan, [
            'symfony.messenger.event.category' => 'messenger.middleware',
            'symfony.messenger.bus.name' => 'default',
            'symfony.messenger.event.current' => '"Symfony\Component\Messenger\Middleware\SendMessageMiddleware" on "default"',
        ]);
        self::assertSpanEventsCount($sendMiddlewareSpan, 0);

        $transportSpan = self::getSpans()[1];
        self::assertSpanName($transportSpan, 'mailer.transport.send');
        self::assertSpanStatus($transportSpan, StatusData::unset());
        self::assertSpanAttributes($transportSpan, []);
        self::assertSpanEventsCount($transportSpan, 0);

        $handleMiddlewareSpan = self::getSpans()[2];
        self::assertSpanName($handleMiddlewareSpan, 'messenger.middleware');
        self::assertSpanStatus($handleMiddlewareSpan, StatusData::ok());
        self::assertSpanAttributes($handleMiddlewareSpan, [
            'symfony.messenger.event.category' => 'messenger.middleware',
            'symfony.messenger.bus.name' => 'default',
            'symfony.messenger.event.current' => '"Symfony\Component\Messenger\Middleware\HandleMessageMiddleware" on "default"',
        ]);
        self::assertSpanEventsCount($handleMiddlewareSpan, 0);

        $tailMiddlewareSpan = self::getSpans()[3];
        self::assertSpanName($tailMiddlewareSpan, 'messenger.middleware');
        self::assertSpanStatus($tailMiddlewareSpan, StatusData::ok());
        self::assertSpanAttributes($tailMiddlewareSpan, [
            'symfony.messenger.event.category' => 'messenger.middleware',
            'symfony.messenger.bus.name' => 'default',
            'symfony.messenger.event.current' => '"Symfony\Component\Messenger\Middleware\StackMiddleware" on "default"',
        ]);
        self::assertSpanEventsCount($tailMiddlewareSpan, 0);

        $mainSpan = self::getSpans()[4];
        self::assertSpanName($mainSpan, 'mailer.send');
        self::assertSpanStatus($mainSpan, StatusData::unset());
        self::assertSpanAttributes($mainSpan, []);
        self::assertSpanEventsCount($mainSpan, 0);
    }

    public function testSendException(): void
    {
        $kernel = self::bootKernel();

        $mailer = $kernel->getContainer()->get('mailer.mailer');

        $email = (new Email())
            ->from('hello@example.com')
            ->to('you@example.com')
            ->subject('Mailer test')
            ->text('A text body')
            ->html('<p>An html body</p>');

        $email
            ->getHeaders()
            ->addTextHeader('X-Transport', 'exception');

        try {
            $mailer->send($email);
        } catch (TransportException $exception) {
        }

        self::assertEmailCount(1);
        self::assertSpansCount(4);

        $sendMiddlewareSpan = self::getSpans()[0];
        self::assertSpanName($sendMiddlewareSpan, 'messenger.middleware');
        self::assertSpanStatus($sendMiddlewareSpan, StatusData::ok());
        self::assertSpanAttributes($sendMiddlewareSpan, [
            'symfony.messenger.event.category' => 'messenger.middleware',
            'symfony.messenger.bus.name' => 'default',
            'symfony.messenger.event.current' => '"Symfony\Component\Messenger\Middleware\SendMessageMiddleware" on "default"',
        ]);
        self::assertSpanEventsCount($sendMiddlewareSpan, 0);

        $transportSpan = self::getSpans()[1];
        self::assertSpanName($transportSpan, 'mailer.transport.send');
        self::assertSpanStatus($transportSpan, new StatusData(StatusCode::STATUS_ERROR, 'Connection could not be established with host "localhost:25": stream_socket_client(): Unable to connect to localhost:25 (Connection refused)'));
        self::assertSpanAttributes($transportSpan, []);
        self::assertSpanEventsCount($transportSpan, 1);

        $handleMiddlewareSpan = self::getSpans()[2];
        self::assertSpanName($handleMiddlewareSpan, 'messenger.middleware');
        self::assertSpanStatus($handleMiddlewareSpan, new StatusData(StatusCode::STATUS_ERROR, 'Handling "Symfony\Component\Mailer\Messenger\SendEmailMessage" failed: Connection could not be established with host "localhost:25": stream_socket_client(): Unable to connect to localhost:25 (Connection refused)'));
        self::assertSpanAttributes($handleMiddlewareSpan, [
            'symfony.messenger.event.category' => 'messenger.middleware',
            'symfony.messenger.bus.name' => 'default',
            'symfony.messenger.event.current' => '"Symfony\Component\Messenger\Middleware\HandleMessageMiddleware" on "default"',
        ]);
        self::assertSpanEventsCount($handleMiddlewareSpan, 1);

        $mainSpan = self::getSpans()[3];
        self::assertSpanName($mainSpan, 'mailer.send');
        self::assertSpanStatus($mainSpan, new StatusData(StatusCode::STATUS_ERROR, 'Connection could not be established with host "localhost:25": stream_socket_client(): Unable to connect to localhost:25 (Connection refused)'));
        self::assertSpanAttributes($mainSpan, []);
        self::assertSpanEventsCount($mainSpan, 1);

        $exceptionEvent = $mainSpan->getEvents()[0];
        self::assertSpanEventName($exceptionEvent, 'exception');
        self::assertSpanEventAttributesSubSet($exceptionEvent, [
            'exception.type' => 'Symfony\Component\Mailer\Exception\TransportException',
            'exception.message' => 'Connection could not be established with host "localhost:25": stream_socket_client(): Unable to connect to localhost:25 (Connection refused)',
        ]);
    }
}
