<?php

namespace YasserElgammal\Green\Tests\Auth;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Auth\Authorizer;
use YasserElgammal\Green\Auth\Policy;
use YasserElgammal\Green\Auth\ForbiddenException;

class Post
{
    public function __construct(public int $authorId) {}
}

class PostPolicy extends Policy
{
    public function edit(?array $actor, Post $post): bool
    {
        if (!$actor) return false;
        return $actor['id'] === $post->authorId;
    }

    public function delete(?array $actor, Post $post): bool
    {
        return false; // Only admins can delete, checked in before()
    }

    public function before(mixed $actor, string $ability): ?bool
    {
        if ($actor && ($actor['role'] ?? '') === 'admin') {
            return true;
        }
        return null;
    }
}

class AuthorizerTest extends TestCase
{
    private Authorizer $authorizer;

    protected function setUp(): void
    {
        $this->authorizer = new Authorizer();
        $this->authorizer->policy(Post::class, PostPolicy::class);
    }

    public function test_it_authorizes_using_policy()
    {
        $post = new Post(42);
        $user = ['id' => 42];
        $otherUser = ['id' => 99];

        $this->assertTrue($this->authorizer->check('edit', $post, $user));
        $this->assertFalse($this->authorizer->check('edit', $post, $otherUser));
    }

    public function test_it_respects_before_hook()
    {
        $post = new Post(42);
        $admin = ['id' => 99, 'role' => 'admin'];

        $this->assertTrue($this->authorizer->check('edit', $post, $admin));
        $this->assertTrue($this->authorizer->check('delete', $post, $admin));
    }

    public function test_it_throws_forbidden_exception()
    {
        $post = new Post(42);
        $otherUser = ['id' => 99];

        $this->expectException(ForbiddenException::class);
        $this->authorizer->authorize('edit', $post, $otherUser);
    }

    public function test_inline_abilities()
    {
        $this->authorizer->define('access-admin', function ($actor) {
            return $actor && ($actor['role'] ?? '') === 'admin';
        });

        $this->assertTrue($this->authorizer->check('access-admin', null, ['role' => 'admin']));
        $this->assertFalse($this->authorizer->check('access-admin', null, ['role' => 'user']));
    }
}
