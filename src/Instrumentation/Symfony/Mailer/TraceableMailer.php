<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Mailer;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

class TraceableMailer implements MailerInterface
{
    public function __construct(
        private TracerInterface $tracer,
        private MailerInterface $mailer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $scope = Context::storage()->scope();
        if (null !== $scope) {
            $this->logger?->debug(sprintf('Using scope "%s"', spl_object_id($scope)));
        } else {
            $this->logger?->debug('No active scope');
        }
        $span = null;

        try {
            $spanBuilder = $this->tracer
                ->spanBuilder('mailer.send')
                ->setSpanKind(SpanKind::KIND_INTERNAL)
                ->setParent($scope?->context())
            ;

            // TODO: Parse RawMessage implementations to set span attributes

            $span = $spanBuilder->startSpan();

            $this->logger?->debug(sprintf('Starting span "%s"', $span->getContext()->getSpanId()));

            $this->mailer->send($message, $envelope);
        } catch (TransportException $exception) {
            if ($span instanceof SpanInterface) {
                $span->recordException($exception);
                $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            }
            throw $exception;
        } finally {
            if ($span instanceof SpanInterface) {
                $this->logger?->debug(sprintf('Ending span "%s"', $span->getContext()->getSpanId()));
                $span->end();
            }
        }
    }
}
