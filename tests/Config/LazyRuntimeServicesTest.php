<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\ErrorHandling\GreenErrorKernel;
use YasserElgammal\Green\Translation\Translator;
use YasserElgammal\Green\Translation\TranslatorManager;
use YasserElgammal\Green\View\View;
use YasserElgammal\Green\View\ViewRenderer;

final class LazyRuntimeServicesTest extends TestCase
{
    private ?Application $application = null;

    protected function tearDown(): void
    {
        $this->application?->make(GreenErrorKernel::class)->unregister();
        parent::tearDown();
    }

    public function testViewAndTranslationServicesResolveOnlyOnFirstUse(): void
    {
        $viewsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'views';
        $this->application = new Application(
            configOverrides: ['view' => ['path' => $viewsPath]],
            basePath: dirname(__DIR__, 2),
        );

        self::assertFalse($this->hasResolvedInstance(ViewRenderer::class));
        self::assertFalse($this->hasResolvedInstance(Translator::class));

        self::assertSame('Hello Green', trim(View::render('lazy', ['name' => 'Green'])));
        self::assertTrue($this->hasResolvedInstance(ViewRenderer::class));

        self::assertInstanceOf(Translator::class, TranslatorManager::getInstance());
        self::assertTrue($this->hasResolvedInstance(Translator::class));
    }

    private function hasResolvedInstance(string $abstract): bool
    {
        $container = (new ReflectionClass($this->application))->getParentClass();
        $instances = $container->getProperty('instances')->getValue($this->application);

        return isset($instances[$abstract]);
    }
}
