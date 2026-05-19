<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Runtime;

use App\Kernel;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
final class ShutdownSemanticsTest extends WebTestCase
{
    public function testClassicModeShutsDownProviderOnTerminate(): void
    {
        $client = static::createClient();
        // Even though FPM reboots the kernel per request in reality, we disable reboot here so the
        // provider state we observe after the request reflects the same instance the subscriber acted on.
        $client->disableReboot();
        $client->request('GET', '/increment/1');
        self::assertResponseIsSuccessful();

        $provider = self::getContainer()->get('open_telemetry.metrics.providers.default');
        self::assertInstanceOf(MeterProviderInterface::class, $provider);

        // shutdown() returns false when the provider is already closed.
        self::assertFalse(
            $provider->shutdown(),
            'In classic mode the kernel.terminate subscriber should have shut down the provider already',
        );
    }
}
