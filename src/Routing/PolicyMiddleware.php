<?php

namespace YasserElgammal\Green\Routing;

use ReflectionMethod;
use YasserElgammal\Green\Auth\PolicyAttribute;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Middleware\MiddlewareInterface;

final class PolicyMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $handler = $request->getAttribute('__green_route_handler');
        $vars = $request->getAttribute('__green_route_vars', []);

        if (is_array($handler) && count($handler) === 2) {
            $this->authorize($handler, is_array($vars) ? $vars : []);
        }

        return $next($request);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array<string, mixed> $vars
     */
    private function authorize(array $handler, array $vars): void
    {
        [$controllerClass, $method] = $handler;
        $reflectionMethod = new ReflectionMethod($controllerClass, $method);
        $policyAttributes = $reflectionMethod->getAttributes(PolicyAttribute::class);

        if ($policyAttributes === []) {
            return;
        }

        $authorizer = authorizer();

        foreach ($policyAttributes as $attribute) {
            /** @var PolicyAttribute $policy */
            $policy = $attribute->newInstance();
            $subject = $vars[$policy->subject] ?? $policy->subject;

            $authorizer->authorize($policy->ability, $subject);
        }
    }
}
