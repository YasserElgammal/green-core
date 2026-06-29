<?php

namespace YasserElgammal\Green\Database\Schema\Grammar;

use PDO;

class MySqlGrammar extends Grammar
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
            $keys = implode('`, `', $primaryKeys);
            $parts[] = "  PRIMARY KEY (`{$keys}`)";
        }

        foreach ($foreignKeys as $fk) {
            $sql = "  CONSTRAINT `{$fk['name']}` FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$fk['reference_table']}` (`{$fk['reference_column']}`)";
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
            "CREATE TABLE `{$table}` (\n{$colsSql}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        foreach ($indexes as $idx) {
            $cols = '`' . implode('`, `', $idx['columns']) . '`';
            $statements[] = "CREATE INDEX `{$idx['name']}` ON `{$table}` ({$cols})";
        }

        return $statements;
    }

    public function compileAlter(string $table, array $operations, array $indexes, array $foreignKeys): array
    {
        $statements = [];

        foreach ($operations as ['action' => $action, 'column' => $col]) {
            switch ($action) {
                case 'add':
                    $statements[] = "ALTER TABLE `{$table}` ADD COLUMN {$col->toSql()}";
                    break;
                case 'modify':
                    $statements[] = "ALTER TABLE `{$table}` MODIFY COLUMN {$col->toSql()}";
                    break;
                case 'drop':
                    $statements[] = "ALTER TABLE `{$table}` DROP COLUMN `{$col}`";
                    break;
            }
        }

        foreach ($indexes as $idx) {
            $cols = '`' . implode('`, `', $idx['columns']) . '`';
            $statements[] = "CREATE INDEX `{$idx['name']}` ON `{$table}` ({$cols})";
        }

        foreach ($foreignKeys as $fk) {
            $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fk['name']}` FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$fk['reference_table']}` (`{$fk['reference_column']}`)";
            if ($fk['on_delete']) {
                $sql .= " ON DELETE {$fk['on_delete']}";
            }
            if ($fk['on_update']) {
                $sql .= " ON UPDATE {$fk['on_update']}";
            }
            $statements[] = $sql;
        }

        return $statements;
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE `{$table}`";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS `{$table}`";
    }

    public function compileHasTable(string $table, string $dbName): string
    {
        return "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$table}'";
    }

    public function compileHasColumn(string $table, string $column, string $dbName): string
    {
        return "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$column}'";
    }

    public function compileDropAllTables(PDO $pdo): array
    {
        $statements = ["SET FOREIGN_KEY_CHECKS = 0;"];

        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?");
        $stmt->execute([$dbName]);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $statements[] = "DROP TABLE `{$table}`";
        }

        $statements[] = "SET FOREIGN_KEY_CHECKS = 1;";

        return $statements;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . $identifier . '`';
    }
}
