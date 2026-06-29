<?php

namespace YasserElgammal\Green\Auth;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class PolicyAttribute
{
    /**
     * @param string $ability The ability to check (e.g. 'delete')
     * @param string $subject The name of the route parameter or bound subject (e.g. 'post')
     */
    public function __construct(
        public readonly string $ability,
        public readonly string $subject
    ) {}
}
