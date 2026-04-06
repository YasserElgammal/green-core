<?php

namespace YasserElgammal\Green\Console;

use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Green Framework', '1.0.0');
    }
}
