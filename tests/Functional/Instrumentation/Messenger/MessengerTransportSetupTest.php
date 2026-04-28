<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Functional\Instrumentation\Messenger;

use App\Kernel;
use Doctrine\DBAL\Connection;
use FriendsOfOpenTelemetry\OpenTelemetryBundle\Instrumentation\Symfony\Messenger\Transport\TraceableFullMessengerTransport;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Env('KERNEL_CLASS', Kernel::class)]
class MessengerTransportSetupTest extends KernelTestCase
{
    private const TABLE_NAME = 'messenger_messages_setup_test';

    protected function setUp(): void
    {
        self::bootKernel();
        $this->dropSetupTable();
    }

    protected function tearDown(): void
    {
        $this->dropSetupTable();
        parent::tearDown();
    }

    public function testSetupTransportsCreatesUnderlyingTable(): void
    {
        self::assertFalse($this->setupTableExists(), 'Pre-condition: table should not exist before the test runs.');

        $tester = new ApplicationTester($this->buildApplication());
        $exit = $tester->run(['command' => 'messenger:setup-transports', 'transport' => 'setup']);

        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertTrue($this->setupTableExists(), 'messenger:setup-transports should have created the underlying table when the doctrine transport is wrapped by trace(...).');
    }

    public function testWrappedDoctrineTransportAdvertisesItsCapabilities(): void
    {
        $transport = self::getContainer()->get('messenger.transport.setup');

        self::assertInstanceOf(TraceableFullMessengerTransport::class, $transport);
    }

    private function buildApplication(): Application
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        return $application;
    }

    private function getConnection(): Connection
    {
        return self::getContainer()->get('doctrine.dbal.app_connection');
    }

    private function setupTableExists(): bool
    {
        $rows = $this->getConnection()->fetchAllAssociative(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name",
            ['name' => self::TABLE_NAME],
        );

        return [] !== $rows;
    }

    private function dropSetupTable(): void
    {
        $this->getConnection()->executeStatement(\sprintf('DROP TABLE IF EXISTS %s', self::TABLE_NAME));
    }
}
