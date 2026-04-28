<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger;

use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class TraceableMessengerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TracerInterface $tracer,
        private ?LoggerInterface $logger = null,
        private string $busName = 'default',
        private string $eventCategory = 'messenger.middleware',
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stack = new TraceableMessengerStack(
            $this->tracer,
            $stack,
            $this->busName,
            $this->eventCategory,
            $this->logger,
        );

        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $exception) {
            $stack->stop($exception);
            throw $exception;
        } finally {
            $stack->stop();
        }
    }
}
