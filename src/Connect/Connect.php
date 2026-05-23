<?php

namespace YasserElgammal\Green\Connect;

use YasserElgammal\Green\Connect\Drivers\FakeDriver;

final class Connect
{
    public function __construct(private readonly ConnectManager $manager)
    {
    }

    public function connection(?string $name = null): PendingRequest
    {
        return $this->manager->request($name);
    }

    /**
     * @param array<string,mixed> $query
     */
    public function get(string $url, array $query = []): ConnectResponse
    {
        return $this->connection()->get($url, $query);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function post(string $url, array $data = []): ConnectResponse
    {
        return $this->connection()->post($url, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function put(string $url, array $data = []): ConnectResponse
    {
        return $this->connection()->put($url, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function patch(string $url, array $data = []): ConnectResponse
    {
        return $this->connection()->patch($url, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function delete(string $url, array $data = []): ConnectResponse
    {
        return $this->connection()->delete($url, $data);
    }

    public function fake(?string $connection = null): FakeDriver
    {
        return $this->manager->fake($connection);
    }

    public function getManager(): ConnectManager
    {
        return $this->manager;
    }
}
