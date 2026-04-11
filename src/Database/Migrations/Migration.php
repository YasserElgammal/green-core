<?php

namespace YasserElgammal\Green\Database\Migrations;

/**
 * Abstract base class for all migrations.
 * Every migration must implement up() and down().
 */
abstract class Migration
{
    /**
     * Run the migration (create/alter tables, insert seed data, etc.)
     */
    abstract public function up(): void;

    /**
     * Reverse the migration.
     */
    abstract public function down(): void;
}
