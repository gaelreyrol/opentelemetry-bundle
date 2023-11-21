<?php

namespace GaelReyrol\OpenTelemetryBundle\OpenTelemetry\Log\LogRecordExporter;

use GaelReyrol\OpenTelemetryBundle\OpenTelemetry\OtlpExporterCompressionEnum;
use GaelReyrol\OpenTelemetryBundle\OpenTelemetry\OtlpExporterFormatEnum;
use OpenTelemetry\SDK\Logs\Exporter\ConsoleExporter;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Registry;

final class ConsoleLogRecordExporterFactory implements LogRecordExporterFactoryInterface
{
    public static function create(
        string $endpoint = null,
        array $headers = [],
        OtlpExporterFormatEnum $format = null,
        OtlpExporterCompressionEnum $compression = null,
    ): LogRecordExporterInterface {
        $transport = Registry::transportFactory('stream')->create('php://stdout', 'application/json');

        return new ConsoleExporter($transport);
    }
}
