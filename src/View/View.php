<?php

namespace YasserElgammal\Green\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use YasserElgammal\Green\Security\Csrf\CsrfConfig;
use YasserElgammal\Green\Security\Csrf\CsrfTokenManager;

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

        // CSRF Twig helpers
        self::$twig->addFunction(new \Twig\TwigFunction('csrf_token', function (): array {
            $manager = new CsrfTokenManager(session(), new CsrfConfig());
            return $manager->generate();
        }));

        self::$twig->addFunction(new \Twig\TwigFunction('csrf_field', function (): string {
            $manager = new CsrfTokenManager(session(), new CsrfConfig());
            $pair    = $manager->generate();

            return '<input type="hidden" name="_csrf_id" value="' . htmlspecialchars($pair['id'], ENT_QUOTES, 'UTF-8') . '">'
                 . '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($pair['token'], ENT_QUOTES, 'UTF-8') . '">';
        }, ['is_safe' => ['html']]));
    }

    public static function render(string $template, array $data = []): string
    {
        if (self::$twig === null) {
            throw new \RuntimeException("View system not initialized. Call View::init() first.");
        }

        if (!str_ends_with($template, '.twig')) {
            $template .= '.twig';
        }

        $output = self::$twig->render($template, $data);

        // Enforce CSRF token presence in POST/PUT/DELETE forms
        if (stripos($output, '<form') !== false) {
            preg_match_all('/(<form[^>]*>)(.*?)<\/form>/is', $output, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $formOpeningTag = $match[1];
                $formContent = $match[2];
                
                if (preg_match('/method=["\']?(POST|PUT|DELETE|PATCH)["\']?/i', $formOpeningTag)) {
                    if (strpos($formContent, 'name="_csrf_token"') === false) {
                        throw new \RuntimeException("Security Exception: CSRF token missing in a form. Please include {{ csrf_field() }} in your POST forms in view: '$template'.");
                    }
                }
            }
        }

        return $output;
    }
}
