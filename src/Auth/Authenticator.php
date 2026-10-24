<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Auth;

use YasserElgammal\Green\Http\Request;

interface Authenticator
{
    public function authenticate(Request $request): ?object;
}
