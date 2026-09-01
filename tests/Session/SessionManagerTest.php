<?php

namespace YasserElgammal\Green\Tests\Session;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\ErrorHandling\GreenErrorKernel;
use YasserElgammal\Green\Session\SessionManager;

final class SessionManagerTest extends TestCase
{
    private ?Application $application = null;

    protected function tearDown(): void
    {
        $this->application?->make(GreenErrorKernel::class)->unregister();
        parent::tearDown();
    }

    public function testConstructionDoesNotStartSession(): void
    {
        $manager = $this->manager();

        self::assertFalse($manager->isStarted());
    }

    public function testFirstSessionOperationStartsSessionLazily(): void
    {
        $manager = $this->manager();

        self::assertNull($manager->get('missing'));
        self::assertTrue($manager->isStarted());
    }

    public function testSessionHelperAndContainerResolveSameLazySingleton(): void
    {
        $this->application = new Application(basePath: dirname(__DIR__, 2));

        $fromContainer = $this->application->make(SessionManager::class);

        self::assertSame($fromContainer, session());
        self::assertFalse($fromContainer->isStarted());
    }

    public function testRegenerateIdPreventsSessionFixationAndKeepsData(): void
    {
        $manager = $this->manager();
        $manager->put('user_id', 42);
        $originalId = $manager->getId();

        self::assertTrue($manager->regenerateId(true));

        self::assertNotSame($originalId, $manager->getId());
        self::assertSame(42, $manager->get('user_id'));
    }

    public function testInvalidateClearsDataAndRotatesSessionId(): void
    {
        $manager = $this->manager();
        $manager->put('user_id', 42);
        $originalId = $manager->getId();

        self::assertTrue($manager->invalidate());

        self::assertNotSame($originalId, $manager->getId());
        self::assertFalse($manager->has('user_id'));
    }

    private function manager(): SessionManager
    {
        return new SessionManager(new Session(new MockArraySessionStorage()));
    }
}
