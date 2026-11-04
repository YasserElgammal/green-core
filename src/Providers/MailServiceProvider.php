<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Config\Typed\MailConfig;
use YasserElgammal\Green\Mail\Mailer;
use YasserElgammal\Green\Mail\MailerInterface;
use YasserElgammal\Green\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Mailer::class, function ($app) {
            $config = $app->make(MailConfig::class);
            return new Mailer($config);
        });

        $this->app->bind(MailerInterface::class, fn ($app) => $app->make(Mailer::class));
    }
}
