<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Log\LoggerProvider;

enum LoggerProviderEnum: string
{
    case Default = 'default';
    case Noop = 'noop';
    case Globals = 'globals';
}
