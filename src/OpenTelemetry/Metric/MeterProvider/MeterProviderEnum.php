<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Metric\MeterProvider;

enum MeterProviderEnum: string
{
    case Noop = 'noop';
    case Default = 'default';
    case Globals = 'globals';
}
