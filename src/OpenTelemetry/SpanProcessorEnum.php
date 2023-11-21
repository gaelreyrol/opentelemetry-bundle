<?php

namespace GaelReyrol\OpenTelemetryBundle\OpenTelemetry;

enum SpanProcessorEnum: string
{
    case Multi = 'multi';
    case Simple = 'simple';
    //    case Batch = 'batch';
    case Noop = 'noop';
}
