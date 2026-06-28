<?php

namespace YasserElgammal\Green\Database\Schema\Grammar;

use PDO;

class SqliteGrammar extends Grammar
{
    public function compileCreate(string $table, array $operations, array $primaryKeys, array $indexes, array $foreignKeys): array
    {
        $parts = [];

        foreach ($operations as ['action' => $action, 'column' => $col]) {
            if ($action === 'add') {
                $parts[] = '  ' . $col->toSql();
            }
        }

        if (!empty($primaryKeys)) {
            $keys = implode('", "', $primaryKeys);
            $parts[] = "  PRIMARY KEY (\"{$keys}\")";
        }

        foreach ($foreignKeys as $fk) {
            $sql = "  FOREIGN KEY (\"{$fk['column']}\") REFERENCES \"{$fk['reference_table']}\" (\"{$fk['reference_column']}\")";
            if ($fk['on_delete']) {
                $sql .= " ON DELETE {$fk['on_delete']}";
            }
            if ($fk['on_update']) {
                $sql .= " ON UPDATE {$fk['on_update']}";
            }
            $parts[] = $sql;
        }

        $colsSql = implode(",\n", $parts);
        $statements = [
            "CREATE TABLE \"{$table}\" (\n{$colsSql}\n)"
        ];

        foreach ($indexes as $idx) {
            $cols = '"' . implode('", "', $idx['columns']) . '"';
            $statements[] = "CREATE INDEX \"{$idx['name']}\" ON \"{$table}\" ({$cols})";
        }

        return $statements;
    }

    public function compileAlter(string $table, array $operations, array $indexes, array $foreignKeys): array
    {
        $statements = [];

        foreach ($operations as ['action' => $action, 'column' => $col]) {
            switch ($action) {
                case 'add':
                    $statements[] = "ALTER TABLE \"{$table}\" ADD COLUMN {$col->toSql()}";
                    break;
                case 'modify':
                    // SQLite does not support MODIFY COLUMN — requires table recreation
                    // For now, we'll skip with a warning comment
                    $statements[] = "-- SQLite does not support MODIFY COLUMN for \"{$table}\"";
                    break;
                case 'drop':
                    $statements[] = "ALTER TABLE \"{$table}\" DROP COLUMN \"{$col}\"";
                    break;
            }
        }

        foreach ($indexes as $idx) {
            $cols = '"' . implode('", "', $idx['columns']) . '"';
            $statements[] = "CREATE INDEX \"{$idx['name']}\" ON \"{$table}\" ({$cols})";
        }

        foreach ($foreignKeys as $fk) {
            // SQLite doesn't support ALTER TABLE ADD CONSTRAINT for FKs
            $statements[] = "-- SQLite: foreign key constraints must be defined at table creation for \"{$table}\"";
        }

        return $statements;
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE \"{$table}\"";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS \"{$table}\"";
    }

    public function compileHasTable(string $table, string $dbName): string
    {
        return "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='{$table}'";
    }

    public function compileHasColumn(string $table, string $column, string $dbName): string
    {
        // SQLite uses PRAGMA; this is a simple approximation
        return "SELECT COUNT(*) FROM pragma_table_info('{$table}') WHERE name='{$column}'";
    }

    public function compileDropAllTables(PDO $pdo): array
    {
        $statements = ["PRAGMA foreign_keys = OFF;"];

        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $statements[] = "DROP TABLE \"{$table}\"";
        }

        $statements[] = "PRAGMA foreign_keys = ON;";

        return $statements;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . $identifier . '"';
    }
}
