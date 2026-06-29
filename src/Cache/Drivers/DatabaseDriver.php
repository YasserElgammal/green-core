<?php

namespace YasserElgammal\Green\Cache\Drivers;

use YasserElgammal\Green\Cache\Contracts\CacheDriverInterface;
use Doctrine\DBAL\Connection;

class DatabaseDriver implements CacheDriverInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table
    ) {}

    public function get(string $key): mixed
    {
        $row = $this->connection->createQueryBuilder()
            ->select('value', 'expires_at')
            ->from($this->table)
            ->where('`key` = :key')
            ->setParameter('key', $key)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return null;
        }

        if (time() >= (int) $row['expires_at']) {
            $this->forget($key);
            return null;
        }

        return unserialize($row['value']);
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $expiresAt = time() + $ttl;
        $serialized = serialize($value);

        // Replace into or Insert on duplicate key update based on DB platform
        // Doctrine DBAL doesn't have a built-in upsert, so we'll check existence

        $exists = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($this->table)
            ->where('`key` = :key')
            ->setParameter('key', $key)
            ->executeQuery()
            ->fetchOne();

        if ($exists) {
            $this->connection->update($this->table, [
                'value' => $serialized,
                'expires_at' => $expiresAt
            ], ['`key`' => $key]);
        } else {
            $this->connection->insert($this->table, [
                '`key`' => $key,
                'value' => $serialized,
                'expires_at' => $expiresAt
            ]);
        }
    }

    public function forget(string $key): bool
    {
        return (bool) $this->connection->delete($this->table, ['`key`' => $key]);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function flush(): void
    {
        $this->connection->executeStatement("TRUNCATE TABLE {$this->table}");
    }
}
