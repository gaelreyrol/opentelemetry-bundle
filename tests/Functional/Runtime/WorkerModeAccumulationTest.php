<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Runtime;

use App\Kernel;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\MeterTestCaseTrait;
use OpenTelemetry\SDK\Metrics\Data\NumberDataPoint;
use OpenTelemetry\SDK\Metrics\Data\Sum;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
#[Env('APP_ENV', 'worker_mode')]
final class WorkerModeAccumulationTest extends WebTestCase
{
    use MeterTestCaseTrait;

    public function testCounterAccumulatesAcrossRequestsInWorkerMode(): void
    {
        $client = static::createClient();
        // FrankenPHP worker mode keeps the kernel alive between requests; KernelBrowser reboots it by
        // default, which would mask the worker-mode behaviour we want to assert here.
        $client->disableReboot();

        $client->request('GET', '/increment/2');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/increment/6');
        self::assertResponseIsSuccessful();

        $provider = self::getContainer()->get('open_telemetry.metrics.providers.default');
        self::assertInstanceOf(MeterProviderInterface::class, $provider);
        self::assertTrue($provider->forceFlush(), 'Provider should still be live after worker-mode terminate');

        $metrics = self::getMetrics();
        $dummyDataPoints = [];
        foreach ($metrics as $metric) {
            if ('dummy' !== $metric->name || !$metric->data instanceof Sum) {
                continue;
            }
            foreach ($metric->data->dataPoints as $point) {
                $dummyDataPoints[] = $point;
            }
        }

        self::assertNotEmpty($dummyDataPoints, 'Counter should have been exported at least once');

        $total = array_sum(array_map(static fn (NumberDataPoint $p): int => $p->value, $dummyDataPoints));
        self::assertSame(
            8,
            $total,
            'Counter increments from request 1 (2) and request 2 (6) must both reach the exporter, summing to 8',
        );
    }
}
