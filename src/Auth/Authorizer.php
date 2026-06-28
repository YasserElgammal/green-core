<?php

namespace YasserElgammal\Green\Auth;

class Authorizer
{
    /**
     * Map of subject types to policy classes.
     * @var array<string, string>
     */
    private array $policies = [];

    /**
     * Map of inline abilities to closures.
     * @var array<string, callable>
     */
    private array $inlineAbilities = [];

    /**
     * Register a policy class for a given subject type.
     */
    public function policy(string $subjectType, string $policyClass): void
    {
        $this->policies[$subjectType] = $policyClass;
    }

    /**
     * Define an inline ability closure.
     */
    public function define(string $ability, callable $callback): void
    {
        $this->inlineAbilities[$ability] = $callback;
    }

    /**
     * Determine if the actor has the given ability for the subject.
     */
    public function check(string $ability, mixed $subject, mixed $actor = null): bool
    {
        // 1. Check inline abilities first
        if (isset($this->inlineAbilities[$ability])) {
            return (bool) ($this->inlineAbilities[$ability])($actor, $subject);
        }

        // 2. Resolve subject type
        $subjectType = is_object($subject) ? get_class($subject) : (is_string($subject) ? $subject : null);

        if (!$subjectType || !isset($this->policies[$subjectType])) {
            // No policy found for this subject type.
            return false;
        }

        // 3. Resolve and invoke policy
        $policyClass = $this->policies[$subjectType];
        $policy = new $policyClass();

        // Check before() hook
        $beforeResult = $policy->before($actor, $ability);
        if ($beforeResult !== null) {
            return $beforeResult;
        }

        if (!method_exists($policy, $ability)) {
            return false;
        }

        return (bool) $policy->$ability($actor, is_string($subject) ? null : $subject);
    }

    /**
     * Enforce that the actor has the given ability, or throw ForbiddenException.
     *
     * @throws ForbiddenException
     */
    public function authorize(string $ability, mixed $subject, mixed $actor = null): void
    {
        if (!$this->check($ability, $subject, $actor)) {
            $subjectType = is_object($subject) ? get_class($subject) : (is_string($subject) ? $subject : 'unknown');
            throw new ForbiddenException($ability, $subjectType);
        }
    }
}
