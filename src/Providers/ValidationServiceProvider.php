<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use Respect\Validation\Factory;

class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Factory::setDefaultInstance(
            (new Factory())->withTranslator(static function (string $message): string {
                if (function_exists('t')) {
                    $translated = t('validation.' . $message);
                    if ($translated !== 'validation.' . $message) {
                        return $translated;
                    }
                }
                return $message;
            })
        );
    }
}
