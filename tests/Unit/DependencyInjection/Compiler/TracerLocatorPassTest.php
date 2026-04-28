<?php

namespace FriendsOfOpenTelemetry\OpenTelemetryBundle\Tests\Unit\DependencyInjection\Compiler;

use FriendsOfOpenTelemetry\OpenTelemetryBundle\DependencyInjection\Compiler\TracerLocatorPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(TracerLocatorPass::class)]
class TracerLocatorPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TracerLocatorPass());

        $container->setDefinition('open_telemetry.instrumentation.console.trace.event_subscriber', new Definition());
        $container->setDefinition('open_telemetry.instrumentation.http_kernel.trace.event_subscriber', new Definition());
        $container->setDefinition('open_telemetry.instrumentation.messenger.worker', new Definition());
    }

    public function testNoTracerLocator(): void
    {
        $this->compile();

        $console = $this->container->getDefinition('open_telemetry.instrumentation.console.trace.event_subscriber');
        self::assertEquals([], $console->getArguments());

        $httpKernel = $this->container->getDefinition('open_telemetry.instrumentation.http_kernel.trace.event_subscriber');
        self::assertEquals([], $httpKernel->getArguments());

        $messenger = $this->container->getDefinition('open_telemetry.instrumentation.messenger.worker');
        self::assertEquals([], $messenger->getArguments());
    }

    public function testTracerLocator(): void
    {
        $this->container->setDefinition('tracer1', new Definition())->addTag('open_telemetry.tracer');
        $this->container->setDefinition('tracer2', new Definition())->addTag('open_telemetry.tracer');

        $this->compile();

        $console = $this->container->getDefinition('open_telemetry.instrumentation.console.trace.event_subscriber');
        self::assertArrayHasKey('$tracerLocator', $console->getArguments());

        $httpKernel = $this->container->getDefinition('open_telemetry.instrumentation.http_kernel.trace.event_subscriber');
        self::assertArrayHasKey('$tracerLocator', $httpKernel->getArguments());

        $messenger = $this->container->getDefinition('open_telemetry.instrumentation.messenger.worker');
        self::assertArrayHasKey('$tracerLocator', $messenger->getArguments());
    }
}
