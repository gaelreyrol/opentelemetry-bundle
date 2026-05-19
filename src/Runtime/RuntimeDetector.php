<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Runtime;

final class RuntimeDetector
{
    public function __construct(
        private readonly RuntimeMode $configured = RuntimeMode::Auto,
    ) {
    }

    public function getMode(): RuntimeMode
    {
        return self::resolve($this->configured);
    }

    public function isLongRunning(): bool
    {
        return $this->getMode()->isLongRunning();
    }

    /**
     * Symfony 7.4's FrankenPhpWorkerRunner sets $_SERVER['APP_RUNTIME_MODE'] = 'web=1&worker=1' on every
     * request inside the worker loop. Detection therefore only returns FrankenPhpWorker once the first
     * request is being handled — it is intentional that calls during container boot see Classic.
     *
     * @param array<string, mixed>|null $server $_SERVER override, for testing
     */
    public static function resolve(RuntimeMode $configured = RuntimeMode::Auto, ?array $server = null): RuntimeMode
    {
        if (RuntimeMode::Auto !== $configured) {
            return $configured;
        }

        $server ??= $_SERVER;

        if (self::isFrankenPhpWorker($server)) {
            return RuntimeMode::FrankenPhpWorker;
        }

        return RuntimeMode::Classic;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function isFrankenPhpWorker(array $server): bool
    {
        $runtimeMode = $server['APP_RUNTIME_MODE'] ?? null;
        if (\is_string($runtimeMode) && str_contains($runtimeMode, 'worker=1')) {
            return true;
        }

        if (!\function_exists('frankenphp_handle_request')) {
            return false;
        }

        $runtime = $server['APP_RUNTIME'] ?? null;

        return \is_string($runtime) && str_contains($runtime, 'FrankenPhp');
    }
}
