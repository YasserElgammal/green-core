<?php

namespace YasserElgammal\Green\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /**
     * @param string|array $method GET, POST, etc.
     * @param string $path
     * @param array $middleware
     */
    public function __construct(
        public string|array $method,
        public string $path,
        public array $middleware = []
    ) {
    }
}
