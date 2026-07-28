<?php

namespace YasserElgammal\Green\View;

class View
{
    private static ?ViewRenderer $renderer = null;
    private static ?\Closure $resolver = null;

    public static function init(string $viewsPath, ?string $cachePath = null, bool $debug = false): void
    {
        self::$resolver = null;
        self::$renderer = new ViewRenderer($viewsPath, $cachePath, $debug);
    }

    /** @param callable(): ViewRenderer $resolver */
    public static function setResolver(callable $resolver): void
    {
        self::$renderer = null;
        self::$resolver = \Closure::fromCallable($resolver);
    }

    public static function render(string $template, array $data = []): string
    {
        return self::renderer()->render($template, $data);
    }

    private static function renderer(): ViewRenderer
    {
        if (self::$renderer !== null) {
            return self::$renderer;
        }

        if (self::$resolver === null) {
            throw new \RuntimeException('View system not initialized. Call View::init() first.');
        }

        $renderer = (self::$resolver)();
        if (!$renderer instanceof ViewRenderer) {
            throw new \RuntimeException('View resolver must return a ViewRenderer instance.');
        }

        return self::$renderer = $renderer;
    }
}
