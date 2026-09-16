<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Database;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\Database\Observer;
use YasserElgammal\Green\Database\ObserverRegistry;
use YasserElgammal\Green\ErrorHandling\GreenErrorKernel;

final class ObserverDependency
{
}

final class ContainerResolvedObserver extends Observer
{
    public function __construct(public readonly ObserverDependency $dependency)
    {
    }
}

final class DatabaseServiceProviderTest extends TestCase
{
    private ?Application $application = null;

    protected function tearDown(): void
    {
        $this->application?->make(GreenErrorKernel::class)->unregister();
        parent::tearDown();
    }

    public function testObserverRegistryIsSharedByTheApplication(): void
    {
        $this->application = new Application(basePath: dirname(__DIR__, 2));

        $first = $this->application->make(ObserverRegistry::class);
        $second = $this->application->make(ObserverRegistry::class);

        self::assertSame($first, $second);
    }

    public function testObserverRegistryResolvesObserversThroughTheApplication(): void
    {
        $this->application = new Application(basePath: dirname(__DIR__, 2));
        $registry = $this->application->make(ObserverRegistry::class);

        $registry->register(StubModel::class, ContainerResolvedObserver::class);

        $observer = $registry->getObservers(StubModel::class)[0];

        self::assertInstanceOf(ContainerResolvedObserver::class, $observer);
        self::assertInstanceOf(ObserverDependency::class, $observer->dependency);
    }
}
