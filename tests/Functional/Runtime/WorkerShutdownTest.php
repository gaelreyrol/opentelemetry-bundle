<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Runtime;

use App\Kernel;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
#[Env('APP_ENV', 'worker_mode')]
final class WorkerShutdownTest extends WebTestCase
{
    public function testWorkerModeDoesNotShutDownProviderOnTerminate(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request('GET', '/increment/1');
        self::assertResponseIsSuccessful();

        $provider = self::getContainer()->get('open_telemetry.metrics.providers.default');
        self::assertInstanceOf(MeterProviderInterface::class, $provider);

        // In worker mode the subscriber must NOT have shut the provider down — forceFlush is the
        // contract instead. shutdown() returning true here confirms the provider was still open.
        self::assertTrue(
            $provider->shutdown(),
            'In worker mode the kernel.terminate subscriber should leave the provider open',
        );
    }
}
