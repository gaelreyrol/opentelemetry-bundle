<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Runtime;

enum RuntimeMode: string
{
    case Auto = 'auto';
    case Classic = 'classic';
    case FrankenPhpWorker = 'frankenphp_worker';

    public function isLongRunning(): bool
    {
        return match ($this) {
            self::FrankenPhpWorker => true,
            self::Classic, self::Auto => false,
        };
    }
}
