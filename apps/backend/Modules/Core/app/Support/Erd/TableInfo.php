<?php

namespace Modules\Core\Support\Erd;

/**
 * A database table with its columns and indexes, attributed to a module.
 */
final readonly class TableInfo
{
    /**
     * @param  array<int, ColumnInfo>  $columns
     * @param  array<int, IndexInfo>  $indexes
     */
    public function __construct(
        public string $table,
        public string $module,
        public array $columns,
        public array $indexes = [],
    ) {}

    public function withModule(string $module): self
    {
        return new self($this->table, $module, $this->columns, $this->indexes);
    }

    public function column(string $name): ?ColumnInfo
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function primaryKeyColumns(): array
    {
        foreach ($this->indexes as $index) {
            if ($index->primary) {
                return $index->columns;
            }
        }

        return [];
    }

    public function isPrimaryKeyColumn(string $name): bool
    {
        return in_array($name, $this->primaryKeyColumns(), true);
    }

    /**
     * True only when a single-column unique index covers exactly this column.
     */
    public function isUniqueColumn(string $name): bool
    {
        foreach ($this->indexes as $index) {
            if ($index->makesColumnUnique($name)) {
                return true;
            }
        }

        return false;
    }

    public function isNullableColumn(string $name): bool
    {
        $column = $this->column($name);

        return $column !== null && $column->nullable;
    }
}
