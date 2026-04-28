<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Instrumentation;

use App\Kernel;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\HttpClient\TraceableHttpClient;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\TracingTestCaseTrait;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenTelemetry\SDK\Trace\StatusData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
final class HttpClientTracingTest extends KernelTestCase
{
    use TracingTestCaseTrait;

    public function testOk(): void
    {
        self::bootKernel();

        $client = new TraceableHttpClient(
            new MockHttpClient(new MockResponse('{"status": "ok"}', [
                'url' => 'http://localhost/ok',
                'http_code' => 200,
                'http_method' => 'GET',
                'response_headers' => ['Content-Type' => 'application/json', 'Content-Length' => '16'],
            ])),
            self::getContainer()->get('open_telemetry.traces.tracers.main'),
            new Psr17Factory(),
        );

        $response = $client->request('GET', 'http://localhost/ok');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status": "ok"}', $response->getContent());
        self::assertSame(['content-type' => ['application/json'], 'content-length' => ['16']], $response->getHeaders());

        self::assertSpansCount(1);

        $mainSpan = self::getSpans()[0];
        self::assertSpanName($mainSpan, 'http.client');
        self::assertSpanStatus($mainSpan, StatusData::unset());
        self::assertSpanAttributes($mainSpan, [
            'url.full' => 'http://localhost/ok',
            'url.scheme' => 'http',
            'url.path' => '/ok',
            'url.query' => '',
            'url.fragment' => '',
            'http.request.method' => 'GET',
            'http.response.body.size' => '16',
            'http.response.status_code' => 200,
        ]);
        self::assertSpanEventsCount($mainSpan, 0);
    }

    public function testFailure(): void
    {
        self::bootKernel();

        $client = new TraceableHttpClient(
            new MockHttpClient(new MockResponse('{"status": "failure"}', [
                'url' => 'http://localhost/failure',
                'http_code' => 500,
                'http_method' => 'GET',
                'response_headers' => ['Content-Type' => 'application/json'],
            ])),
            self::getContainer()->get('open_telemetry.traces.tracers.main'),
            new Psr17Factory(),
        );

        $response = $client->request('GET', 'http://localhost/failure');

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        try {
            self::assertSame('{"status": "failure"}', $response->getContent());
        } catch (ServerException) {
        }

        self::assertSpansCount(1);

        $mainSpan = self::getSpans()[0];
        self::assertSpanName($mainSpan, 'http.client');
        self::assertSpanStatus($mainSpan, StatusData::error());
        self::assertSpanAttributes($mainSpan, [
            'url.full' => 'http://localhost/failure',
            'url.scheme' => 'http',
            'url.path' => '/failure',
            'url.query' => '',
            'url.fragment' => '',
            'http.request.method' => 'GET',
            'http.response.status_code' => 500,
        ]);
        self::assertSpanEventsCount($mainSpan, 0);
    }

    public function testException(): void
    {
        self::bootKernel();

        $client = new TraceableHttpClient(
            new MockHttpClient(new MockResponse((static function (): \Generator {
                yield new TransportException('Error at transport level');
            })())),
            self::getContainer()->get('open_telemetry.traces.tracers.main'),
            new Psr17Factory(),
        );

        $response = $client->request('GET', 'http://localhost');

        try {
            $response->getContent();
        } catch (TransportException) {
        }

        self::assertSpansCount(1);

        $mainSpan = self::getSpans()[0];
        self::assertSpanName($mainSpan, 'http.client');
        self::assertSpanStatus($mainSpan, StatusData::unset());
        self::assertSpanAttributes($mainSpan, [
            'url.full' => 'http://localhost',
            'url.scheme' => 'http',
            'url.path' => '',
            'url.query' => '',
            'url.fragment' => '',
            'http.request.method' => 'GET',
            'http.response.status_code' => 200,
        ]);
        self::assertSpanEventsCount($mainSpan, 0);
    }

    public function testStream(): void
    {
        self::bootKernel();

        $client = new TraceableHttpClient(
            new MockHttpClient(new MockResponse((static function (): \Generator {
                yield '{"data": 1}';
                yield '{"data": 2}';
                yield '{"data": 3}';
                yield '{"data": 4}';
            })(), [
                'url' => 'http://localhost/stream',
                'http_code' => 200,
                'http_method' => 'GET',
                'response_headers' => ['Content-Type' => 'application/json'],
            ])),
            self::getContainer()->get('open_telemetry.traces.tracers.main'),
            new Psr17Factory(),
        );

        $response = $client->request('GET', 'http://localhost/stream');

        $chunks = [];
        foreach ($client->stream($response) as $chunkResponse => $chunk) {
            self::assertSame($response, $chunkResponse);

            $chunks[] = $chunk->getContent();
        }

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            '',
            '{"data": 1}',
            '{"data": 2}',
            '{"data": 3}',
            '{"data": 4}',
            '',
        ], $chunks);
        self::assertSame(['content-type' => ['application/json']], $response->getHeaders());

        self::assertSpansCount(1);

        $mainSpan = self::getSpans()[0];
        self::assertSpanName($mainSpan, 'http.client');
        self::assertSpanStatus($mainSpan, StatusData::unset());
        self::assertSpanAttributes($mainSpan, [
            'url.full' => 'http://localhost/stream',
            'url.scheme' => 'http',
            'url.path' => '/stream',
            'url.query' => '',
            'url.fragment' => '',
            'http.request.method' => 'GET',
            'http.response.status_code' => 200,
        ]);
        self::assertSpanEventsCount($mainSpan, 0);
    }
}
