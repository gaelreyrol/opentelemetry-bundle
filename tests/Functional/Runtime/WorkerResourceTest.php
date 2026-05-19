<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Runtime;

use App\Kernel;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\Incubating\Attributes\ProcessIncubatingAttributes;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
final class WorkerResourceTest extends KernelTestCase
{
    public function testResourceContainsProcessPid(): void
    {
        self::bootKernel();

        $resource = self::getContainer()->get('open_telemetry.resource_info');
        self::assertInstanceOf(ResourceInfo::class, $resource);

        $attrs = $resource->getAttributes()->toArray();
        self::assertArrayHasKey(ProcessIncubatingAttributes::PROCESS_PID, $attrs);
        self::assertSame(getmypid(), $attrs[ProcessIncubatingAttributes::PROCESS_PID]);
    }
}
