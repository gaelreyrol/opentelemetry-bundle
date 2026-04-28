<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextStorageScopeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Wraps a middleware stack to create one span per middleware in the chain.
 *
 * Follows a "stop previous, start new" pattern (like Symfony's own TraceableStack
 * with its Stopwatch): each call to next() closes the span/scope from the prior
 * call before opening a new one, so at most one scope is active at any time.
 *
 * A simpler alternative would be to drop this class entirely and wrap the whole
 * $stack->next()->handle() chain in a single span inside TraceableMessengerMiddleware
 * (one activate/detach pair in try/finally). That removes per-middleware timing
 * granularity but eliminates scope management complexity.
 */
class TraceableMessengerStack implements StackInterface
{
    private ?string $currentEvent = null;
    private ?ContextStorageScopeInterface $currentScope = null;
    private ?SpanInterface $currentSpan = null;
    private ?ContextInterface $parentContext = null;

    public function __construct(
        private TracerInterface $tracer,
        private StackInterface $stack,
        private string $busName,
        private string $eventCategory,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function next(): MiddlewareInterface
    {
        // "Stop previous" — close the span/scope from the prior next() call
        if (null !== $this->currentScope) {
            $this->logger?->debug(sprintf('Detaching scope "%s"', spl_object_id($this->currentScope)));
            $this->currentScope->detach();
            $this->currentScope = null;

            if (null !== $this->currentSpan) {
                $this->currentSpan->setStatus(StatusCode::STATUS_OK);
                $this->logger?->debug(sprintf('Ending span "%s"', $this->currentSpan->getContext()->getSpanId()));
                $this->currentSpan->end();
                $this->currentSpan = null;
            }
        }

        // Capture the parent context once (on the first call)
        $this->parentContext ??= Context::getCurrent();

        // "Start new"
        $span = $this->tracer
            ->spanBuilder('messenger.middleware')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setParent($this->parentContext)
            ->setAttribute('symfony.messenger.event.category', $this->eventCategory)
            ->setAttribute('symfony.messenger.bus.name', $this->busName)
            ->startSpan();

        $this->logger?->debug(sprintf('Starting span "%s"', $span->getContext()->getSpanId()));

        if ($this->stack === $nextMiddleware = $this->stack->next()) {
            $this->currentEvent = 'Tail';
        } else {
            $this->currentEvent = sprintf('"%s"', get_debug_type($nextMiddleware));
        }
        $this->currentEvent .= sprintf(' on "%s"', $this->busName);

        $span->setAttribute('symfony.messenger.event.current', $this->currentEvent);

        $context = $span->storeInContext($this->parentContext);
        $this->currentScope = Context::storage()->attach($context);
        $this->currentSpan = $span;

        return $nextMiddleware;
    }

    public function stop(?\Throwable $throwable = null): void
    {
        if (null !== $this->currentScope) {
            $this->logger?->debug(sprintf('Detaching scope "%s"', spl_object_id($this->currentScope)));
            $this->currentScope->detach();
            $this->currentScope = null;
        }

        if (null !== $this->currentSpan) {
            if (null !== $throwable) {
                $this->currentSpan->recordException($throwable);
                $this->currentSpan->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
            } else {
                $this->currentSpan->setStatus(StatusCode::STATUS_OK);
            }
            $this->logger?->debug(sprintf('Ending span "%s"', $this->currentSpan->getContext()->getSpanId()));
            $this->currentSpan->end();
            $this->currentSpan = null;
        }

        $this->currentEvent = null;
    }

    public function __clone()
    {
        $this->stack = clone $this->stack;
        $this->currentScope = null;
        $this->currentSpan = null;
        $this->parentContext = null;
        $this->currentEvent = null;
    }
}
