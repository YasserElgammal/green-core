<?php

namespace YasserElgammal\Green\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class View
{
    protected static ?Environment $twig = null;

    public static function init(string $viewsPath): void
    {
        $loader = new FilesystemLoader($viewsPath);
        self::$twig = new Environment($loader, [
            'cache' => false,
        ]);

        self::$twig->addFunction(new \Twig\TwigFunction('session', fn() => session()));
        self::$twig->addFunction(new \Twig\TwigFunction('t', fn(string $key, array $replace = [], ?string $locale = null) => t($key, $replace, $locale)));
        self::$twig->addFunction(new \Twig\TwigFunction('trans_choice', fn(string $key, int $count, array $replace = [], ?string $locale = null) => trans_choice($key, $count, $replace, $locale)));
    }

    public static function render(string $template, array $data = []): string
    {
        if (self::$twig === null) {
            throw new \RuntimeException("View system not initialized. Call View::init() first.");
        }

        if (!str_ends_with($template, '.twig')) {
            $template .= '.twig';
        }

        return self::$twig->render($template, $data);
    }
}
