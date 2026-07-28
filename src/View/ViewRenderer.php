<?php

namespace YasserElgammal\Green\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Security\Csrf\CsrfConfig;
use YasserElgammal\Green\Security\Csrf\CsrfTokenManager;

final class ViewRenderer
{
    private Environment $twig;

    public function __construct(
        string $viewsPath,
        ?string $cachePath = null,
        bool $debug = false,
    ) {
        $this->twig = new Environment(new FilesystemLoader($viewsPath), [
            'cache' => $cachePath ?: false,
            'debug' => $debug,
            'auto_reload' => $debug,
        ]);

        $this->registerFunctions();
    }

    public function render(string $template, array $data = []): string
    {
        if (!str_ends_with($template, '.twig')) {
            $template .= '.twig';
        }

        $output = $this->twig->render($template, $data);
        $this->guardCsrfFields($template, $output);

        return $output;
    }

    private function registerFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('session', fn () => session()));
        $this->twig->addFunction(new TwigFunction(
            't',
            fn (string $key, array $replace = [], ?string $locale = null) => t($key, $replace, $locale),
        ));
        $this->twig->addFunction(new TwigFunction(
            'trans_choice',
            fn (string $key, int $count, array $replace = [], ?string $locale = null) => trans_choice($key, $count, $replace, $locale),
        ));
        $this->twig->addFunction(new TwigFunction('current_route', fn () => Request::capture()->getPath()));
        $this->twig->addFunction(new TwigFunction('csrf_token', function (): array {
            return $this->csrfManager()->generate();
        }));
        $this->twig->addFunction(new TwigFunction('csrf_field', function (): string {
            $pair = $this->csrfManager()->generate();

            return '<input type="hidden" name="_csrf_id" value="' . htmlspecialchars($pair['id'], ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($pair['token'], ENT_QUOTES, 'UTF-8') . '">';
        }, ['is_safe' => ['html']]));
    }

    private function csrfManager(): CsrfTokenManager
    {
        return new CsrfTokenManager(session(), new CsrfConfig());
    }

    private function guardCsrfFields(string $template, string $output): void
    {
        if (stripos($output, '<form') === false) {
            return;
        }

        preg_match_all('/(<form[^>]*>)(.*?)<\/form>/is', $output, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $formOpeningTag = $match[1];
            $formContent = $match[2];

            if (
                preg_match('/method=["\']?(POST|PUT|DELETE|PATCH)["\']?/i', $formOpeningTag)
                && !str_contains($formContent, 'name="_csrf_token"')
            ) {
                throw new \RuntimeException(
                    "Security Exception: CSRF token missing in a form. "
                    . "Please include {{ csrf_field() }} in your POST forms in view: '{$template}'.",
                );
            }
        }
    }
}
