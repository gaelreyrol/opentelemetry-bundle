<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry;

enum ProviderSource: string
{
    case Di = 'di';
    case Globals = 'globals';
}
