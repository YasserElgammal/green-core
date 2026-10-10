<?php
declare(strict_types=1);

namespace YasserElgammal\Green\Queue\Drivers;

use YasserElgammal\Green\Queue\Contracts\QueueDriverInterface;
use Doctrine\DBAL\Connection;

final class DatabaseDriver implements QueueDriverInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
    ) {}

    public function push(string $queue, string $payload, int $delay = 0): string|int
    {
        $now = date('Y-m-d H:i:s');
        $availableAt = date('Y-m-d H:i:s', time() + $delay);
        
        $this->connection->insert($this->table, [
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
            'available_at' => $availableAt,
            'created_at' => $now,
        ]);
        
        return (int) $this->connection->lastInsertId();
    }

    public function pop(string $queue): ?array
    {
        // Wrap in transaction to ensure atomicity
        return $this->connection->transactional(function (Connection $conn) use ($queue) {
            $now = date('Y-m-d H:i:s');
            
            $query = $conn->createQueryBuilder()
                ->select('*')
                ->from($this->table)
                ->where('queue = :queue')
                ->andWhere('available_at <= :now')
                ->andWhere('reserved_at IS NULL')
                ->orderBy('id', 'ASC')
                ->setMaxResults(1)
                ->setParameters([
                    'queue' => $queue,
                    'now' => $now,
                ]);

            // Prevent rows locking timeout (MySQL specific syntax, using plain select for DBAL compatibility)
            // A more robust implementation would use FOR UPDATE SKIP LOCKED if supported by driver
            $job = $query->executeQuery()->fetchAssociative();

            if (!$job) {
                return null;
            }

            // Mark as reserved and increment attempts
            $conn->update(
                $this->table,
                [
                    'reserved_at' => $now,
                    'attempts' => $job['attempts'] + 1,
                ],
                ['id' => $job['id']]
            );

            return [
                'id' => $job['id'],
                'payload' => $job['payload'],
                'attempts' => (int) $job['attempts'] + 1,
            ];
        });
    }

    public function delete(string $queue, string|int $id): void
    {
        $this->connection->delete($this->table, [
            'id' => $id,
        ]);
    }

    public function release(string $queue, string|int $id, int $delay = 0): void
    {
        $availableAt = date('Y-m-d H:i:s', time() + $delay);
        
        $this->connection->update(
            $this->table,
            [
                'reserved_at' => null,
                'available_at' => $availableAt,
            ],
            ['id' => $id]
        );
    }
}
