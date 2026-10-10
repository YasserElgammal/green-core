<?php
declare(strict_types=1);

namespace YasserElgammal\Green\Queue;

use YasserElgammal\Green\Queue\Contracts\QueueDriverInterface;
use YasserElgammal\Green\Queue\Contracts\JobInterface;
use YasserElgammal\Green\Queue\Drivers\DatabaseDriver;
use YasserElgammal\Green\Queue\Drivers\SyncDriver;
use YasserElgammal\Green\Database\Database;
use Closure;
use RuntimeException;

final class QueueManager
{
    /** @var array<string, QueueDriverInterface> */
    private array $connections = [];

    /** @var array<string, Closure> */
    private array $customCreators = [];

    public function __construct(private readonly array $config) {}

    // ── Dispatch (proxy to driver) ──────────────────────────────

    /**
     * Dispatch a job onto the queue.
     */
    public function dispatch(
        JobInterface $job,
        string $queue = 'default',
        ?string $connection = null,
    ): string|int {
        $payload = $this->encode($job);
        return $this->connection($connection)->push($queue, $payload);
    }

    // ── Connection Resolution ───────────────────────────────────

    /**
     * Get a queue driver by connection name (or the default).
     */
    public function connection(?string $name = null): QueueDriverInterface
    {
        $name = $name ?: ($this->config['default'] ?? 'sync');

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    /**
     * Register a custom driver creator.
     */
    public function extend(string $driver, Closure $callback): void
    {
        $this->customCreators[$driver] = $callback;
    }

    // ── Serialization (internal) ────────────────────────────────

    /** @internal */
    public function encode(JobInterface $job): string
    {
        return json_encode([
            'class' => get_class($job),
            'data' => serialize($job),
        ], JSON_THROW_ON_ERROR);
    }

    /** @internal */
    public function decode(string $payload): JobInterface
    {
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        
        if (!isset($data['class'], $data['data'])) {
            throw new RuntimeException('Invalid queue payload format.');
        }

        $job = unserialize($data['data']);
        
        if (!$job instanceof JobInterface) {
            throw new RuntimeException("Decoded job does not implement JobInterface: {$data['class']}");
        }

        return $job;
    }

    // ── Private Resolution ──────────────────────────────────────

    private function resolve(string $name): QueueDriverInterface
    {
        $config = $this->config['connections'][$name] ?? null;

        if ($config === null) {
            throw new RuntimeException("Queue connection [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return ($this->customCreators[$config['driver']])($config);
        }

        $method = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        throw new RuntimeException("Queue driver [{$config['driver']}] is not supported.");
    }

    private function createDatabaseDriver(array $config): QueueDriverInterface
    {
        $connectionName = $config['connection'] ?? null;
        $connection = Database::getConnection($connectionName)->getNativeConnection();
        $table = $config['table'] ?? 'queue_jobs';

        return new DatabaseDriver($connection, $table);
    }

    private function createSyncDriver(array $config): QueueDriverInterface
    {
        return new SyncDriver();
    }
}
