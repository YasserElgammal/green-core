<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\Seeders;

/**
 * Seeder is the abstract base for all database seeders.
 *
 * Each seeder implements run() and populates one or more tables
 * using Green's Table Gateway directly. There is no hidden magic —
 * the seeder constructs its own Table instances and calls insert().
 *
 * Optional truncation: declare the table names you want cleared
 * before seeding in the $truncate property. The SeederRunner will
 * handle truncation before calling run().
 */
abstract class Seeder
{
    /**
     * Table names to truncate before run() is called.
     * An empty array means no truncation (default).
     *
     * Example:
     *   protected array $truncate = ['users', 'roles'];
     *
     * @var string[]
     */
    protected array $truncate = [];

    /**
     * Populate the database with seed data.
     */
    abstract public function run(): void;

    /**
     * Return the list of tables this seeder wants truncated.
     *
     * @return string[]
     */
    public function getTruncateTables(): array
    {
        return $this->truncate;
    }
}
