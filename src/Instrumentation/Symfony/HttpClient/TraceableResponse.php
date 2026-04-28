<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\HttpClient;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\HttpIncubatingAttributes;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Response\StreamableInterface;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TraceableResponse implements ResponseInterface, StreamableInterface
{
    public function __construct(
        public readonly HttpClientInterface $client,
        public readonly ResponseInterface $response,
        public readonly ?SpanInterface $span,
        public readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        try {
            if (method_exists($this->response, '__destruct')) {
                $this->response->__destruct();
            }
        } finally {
            $this->endSpan();
        }
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->response->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        try {
            return $this->response->getContent($throw);
        } finally {
            $this->endSpan();
        }
    }

    /**
     * @return array<mixed>
     */
    public function toArray(bool $throw = true): array
    {
        try {
            return $this->response->toArray($throw);
        } finally {
            $this->endSpan();
        }
    }

    public function cancel(): void
    {
        $this->response->cancel();
        $this->endSpan();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    public function toStream(bool $throw = true)
    {
        if ($throw) {
            $this->response->getHeaders();
        }

        if ($this->response instanceof StreamableInterface) {
            return $this->response->toStream(false);
        }

        return StreamWrapper::createResource($this->response, $this->client);
    }

    /**
     * @internal
     *
     * @param iterable<TraceableResponse|ResponseInterface> $responses
     *
     * @return \Generator<TraceableResponse, ChunkInterface>
     */
    public static function stream(HttpClientInterface $client, iterable $responses, ?float $timeout): \Generator
    {
        /** @var \SplObjectStorage<ResponseInterface, TraceableResponse> $traceableMap */
        $traceableMap = new \SplObjectStorage();
        $wrappedResponses = [];

        foreach ($responses as $response) {
            if (!$response instanceof self) {
                throw new \TypeError(sprintf('"%s::stream()" expects parameter 1 to be an iterable of TraceableResponse objects, "%s" given.', TraceableHttpClient::class, get_debug_type($response)));
            }

            $traceableMap[$response->response] = $response;
            $wrappedResponses[] = $response->response;
        }

        foreach ($client->stream($wrappedResponses, $timeout) as $response => $chunk) {
            $traceableResponse = $traceableMap[$response];
            $traceableResponse->endSpan();

            yield $traceableResponse => $chunk;
        }
    }

    private function endSpan(): void
    {
        if (null === $this->span) {
            return;
        }

        $statusCode = $this->response->getStatusCode();
        if (0 !== $statusCode && $this->span->isRecording()) {
            $headers = $this->response->getHeaders(false);
            if (isset($headers['content-length'][0])) {
                $this->span->setAttribute(HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE, $headers['content-length'][0]);
            }

            $this->span->setAttribute(HttpAttributes::HTTP_RESPONSE_STATUS_CODE, $statusCode);

            if ($statusCode >= 400 && $statusCode < 600) {
                $this->span->setAttribute(HttpAttributes::HTTP_RESPONSE_STATUS_CODE, $statusCode);
                $this->span->setStatus(StatusCode::STATUS_ERROR);
            }
        }

        $this->logger?->debug(sprintf('Ending span "%s"', $this->span->getContext()->getSpanId()));
        $this->span->end();
    }
}
