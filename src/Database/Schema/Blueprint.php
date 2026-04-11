<?php

namespace YasserElgammal\Green\Database\Schema;

use PDO;

class Blueprint
{
    /** @var array<string, array{action: string, column: Column|string}> */
    private array $operations = [];

    /** Pending primary key columns */
    private array $primaryKeys = [];

    /** Table indexes to add */
    private array $indexes = [];

    /** Table foreign keys to add */
    private array $foreignKeys = [];

    public function __construct(
        private readonly string $table,
        private readonly PDO    $pdo,
        private readonly bool   $dryRun  = false,
        private readonly bool   $safe    = false,
    ) {}

    // ─── Column Shortcuts ──────────────────────────────────────────────────

    public function id(): Column
    {
        $col = Column::bigInteger('id')->autoIncrement();
        $this->primaryKeys[] = 'id';
        return $this->add($col);
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->add(Column::string($name, $length));
    }

    public function uuid(string $name): Column
    {
        return $this->add(Column::uuid($name));
    }

    public function integer(string $name): Column
    {
        return $this->add(Column::integer($name));
    }

    public function bigInteger(string $name): Column
    {
        return $this->add(Column::bigInteger($name));
    }

    public function text(string $name): Column
    {
        return $this->add(Column::text($name));
    }

    public function boolean(string $name): Column
    {
        return $this->add(Column::boolean($name));
    }

    public function timestamp(string $name): Column
    {
        return $this->add(Column::timestamp($name));
    }

    public function date(string $name): Column
    {
        return $this->add(Column::date($name));
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->add(Column::decimal($name, $precision, $scale));
    }

    public function json(string $name): Column
    {
        return $this->add(Column::json($name));
    }

    public function timestamps(): void
    {
        $this->add(Column::timestamp('created_at')->nullable()->default(null));
        $this->add(Column::timestamp('updated_at')->nullable()->default(null));
    }

    // ─── Core Operations ───────────────────────────────────────────────────

    public function add(Column $column): Column
    {
        $this->operations[$column->name] = ['action' => 'add', 'column' => $column];
        return $column;
    }

    public function drop(string $columnName): void
    {
        if ($this->safe) {
            throw new \RuntimeException(
                "Safe mode: DROP COLUMN `{$columnName}` on `{$this->table}` requires --force."
            );
        }

        $this->operations[$columnName] = ['action' => 'drop', 'column' => $columnName];
    }

    public function modify(Column $column): Column
    {
        $this->operations[$column->name] = ['action' => 'modify', 'column' => $column];
        return $column;
    }

    public function ensure(Column $column): void
    {
        $existing = $this->fetchColumnInfo($column->name);

        if ($existing === null) {
            $this->operations[$column->name] = ['action' => 'add', 'column' => $column];
            return;
        }

        if ($this->columnDiffers($existing, $column)) {
            $this->operations[$column->name] = ['action' => 'modify', 'column' => $column];
            return;
        }
    }

    public function index(array|string $columns, ?string $name = null): void
    {
        $cols = (array) $columns;
        $indexName = $name ?? 'idx_' . $this->table . '_' . implode('_', $cols);

        $this->indexes[] = [
            'name' => $indexName,
            'columns' => $cols,
        ];
    }

    public function foreign(
        string $column,
        string $referenceTable,
        string $referenceColumn = 'id',
        ?string $name = null,
        ?string $onDelete = null,
        ?string $onUpdate = null
    ): void {
        $constraintName = $name ?? "fk_{$this->table}_{$column}";

        $this->foreignKeys[] = [
            'name' => $constraintName,
            'column' => $column,
            'reference_table' => $referenceTable,
            'reference_column' => $referenceColumn,
            'on_delete' => $onDelete,
            'on_update' => $onUpdate,
        ];
    }

    public function foreignId(
        string $column,
        string $referenceTable,
        string $referenceColumn = 'id',
        bool $nullable = false,
        ?string $name = null,
        ?string $onDelete = null,
        ?string $onUpdate = null
    ): Column {
        $col = Column::bigInteger($column);

        if ($nullable) {
            $col->nullable();
        }

        $this->add($col);

        $this->foreign(
            column: $column,
            referenceTable: $referenceTable,
            referenceColumn: $referenceColumn,
            name: $name,
            onDelete: $onDelete,
            onUpdate: $onUpdate
        );

        return $col;
    }

    // ─── SQL Generation ────────────────────────────────────────────────────

    public function buildCreateStatements(): array
    {
        $parts = [];

        foreach ($this->operations as ['action' => $action, 'column' => $col]) {
            if ($action === 'add') {
                $parts[] = '  ' . $col->toSql();
            }
        }

        if (!empty($this->primaryKeys)) {
            $keys = implode('`, `', $this->primaryKeys);
            $parts[] = "  PRIMARY KEY (`{$keys}`)";
        }

        foreach ($this->foreignKeys as $fk) {
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
            "CREATE TABLE `{$this->table}` (\n{$colsSql}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        foreach ($this->indexes as $idx) {
            $cols = '`' . implode('`, `', $idx['columns']) . '`';
            $statements[] = "CREATE INDEX `{$idx['name']}` ON `{$this->table}` ({$cols})";
        }

        return $statements;
    }

    public function buildAlterStatements(): array
    {
        $statements = [];

        foreach ($this->operations as ['action' => $action, 'column' => $col]) {
            switch ($action) {
                case 'add':
                    $statements[] = "ALTER TABLE `{$this->table}` ADD COLUMN {$col->toSql()}";
                    break;

                case 'modify':
                    $statements[] = "ALTER TABLE `{$this->table}` MODIFY COLUMN {$col->toSql()}";
                    break;

                case 'drop':
                    $statements[] = "ALTER TABLE `{$this->table}` DROP COLUMN `{$col}`";
                    break;
            }
        }

        foreach ($this->indexes as $idx) {
            $cols = '`' . implode('`, `', $idx['columns']) . '`';
            $statements[] = "CREATE INDEX `{$idx['name']}` ON `{$this->table}` ({$cols})";
        }

        foreach ($this->foreignKeys as $fk) {
            $sql = "ALTER TABLE `{$this->table}` ADD CONSTRAINT `{$fk['name']}` FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$fk['reference_table']}` (`{$fk['reference_column']}`)";

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

    // ─── Private Helpers ───────────────────────────────────────────────────

    private function fetchColumnInfo(string $columnName): ?array
    {
        $dbName = $this->pdo->query('SELECT DATABASE()')->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ");
        $stmt->execute([$dbName, $this->table, $columnName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function columnDiffers(array $existing, Column $desired): bool
    {
        $def = $desired->toDefinitionArray();

        $dbType = strtoupper($existing['COLUMN_TYPE']);

        if ($dbType !== strtoupper($def['type'])) {
            return true;
        }

        $dbNullable = strtolower($existing['IS_NULLABLE']) === 'yes';
        if ($dbNullable !== $def['nullable']) {
            return true;
        }

        $dbDefault = $existing['COLUMN_DEFAULT'];
        $wantDefault = $def['default'];

        if ($wantDefault === '__none__') {
            return false;
        }

        if ((string) $dbDefault !== (string) $wantDefault) {
            return true;
        }

        return false;
    }
}
