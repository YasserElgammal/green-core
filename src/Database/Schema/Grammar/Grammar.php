<?php

namespace YasserElgammal\Green\Database\Schema\Grammar;

use YasserElgammal\Green\Database\Schema\Column;

/**
 * Abstract Grammar — defines the SQL generation contract
 * for all database platforms.
 */
abstract class Grammar
{
    abstract public function compileCreate(string $table, array $operations, array $primaryKeys, array $indexes, array $foreignKeys): array;
    abstract public function compileAlter(string $table, array $operations, array $indexes, array $foreignKeys): array;
    abstract public function compileDrop(string $table): string;
    abstract public function compileDropIfExists(string $table): string;
    abstract public function compileHasTable(string $table, string $dbName): string;
    abstract public function compileHasColumn(string $table, string $column, string $dbName): string;
    abstract public function compileDropAllTables(\PDO $pdo): array;
    abstract public function quoteIdentifier(string $identifier): string;
}
