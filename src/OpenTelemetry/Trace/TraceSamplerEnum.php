<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\OpenTelemetry\Trace;

enum TraceSamplerEnum: string
{
    case AlwaysOff = 'always_off';
    case AlwaysOn = 'always_on';
    case ParentBasedAlwaysOff = 'parent_based_always_off';
    case ParentBasedAlwaysOn = 'parent_based_always_on';
    case ParentBasedTraceIdRatio = 'parent_based_trace_id_ratio';
    case TraceIdRatio = 'trace_id_ratio';
    case AttributeBased = 'attribute_based';
    case ExpressionBased = 'expression_based';
}
