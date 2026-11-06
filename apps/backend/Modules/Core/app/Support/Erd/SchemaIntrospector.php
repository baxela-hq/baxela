<?php

namespace Modules\Core\Support\Erd;

use Illuminate\Support\Facades\Schema;

/**
 * Reads the live database through Laravel's native schema introspection
 * (no doctrine/dbal): tables, columns, indexes and foreign keys.
 */
class SchemaIntrospector
{
    public function snapshot(): SchemaSnapshot
    {
        /** @var array<int, array{name: string}> $rawTables */
        $rawTables = Schema::getTables();

        $tables = [];
        foreach ($rawTables as $rawTable) {
            $name = $rawTable['name'];
            $tables[$name] = new TableInfo($name, '', $this->columns($name), $this->indexes($name));
        }
        ksort($tables);

        return new SchemaSnapshot($tables, $this->foreignKeys($tables));
    }

    /**
     * @return array<int, ColumnInfo>
     */
    protected function columns(string $table): array
    {
        /** @var array<int, array{name: string, type: string, nullable: bool, auto_increment?: bool}> $raw */
        $raw = Schema::getColumns($table);

        $columns = [];
        foreach ($raw as $column) {
            $columns[] = new ColumnInfo(
                $column['name'],
                $column['type'],
                (bool) $column['nullable'],
                (bool) ($column['auto_increment'] ?? false),
            );
        }

        return $columns;
    }

    /**
     * @return array<int, IndexInfo>
     */
    protected function indexes(string $table): array
    {
        /** @var array<int, array{name: string, columns: array<int, string>, primary?: bool, unique?: bool, type?: string}> $raw */
        $raw = Schema::getIndexes($table);

        $indexes = [];
        foreach ($raw as $index) {
            $type = strtolower((string) ($index['type'] ?? ''));
            $indexes[] = new IndexInfo(
                $index['name'],
                array_values($index['columns']),
                (bool) ($index['primary'] ?? false) || $type === 'primary',
                (bool) ($index['unique'] ?? false) || $type === 'unique',
            );
        }

        return $indexes;
    }

    /**
     * One solid edge per enforced foreign key, enriched with nullability and
     * single-column uniqueness from the introspected tables.
     *
     * @param  array<string, TableInfo>  $tables
     * @return array<int, ErdEdge>
     */
    protected function foreignKeys(array $tables): array
    {
        $edges = [];
        foreach ($tables as $table => $tableInfo) {
            /** @var array<int, array{columns: array<int, string>, foreign_table: string, foreign_columns: array<int, string>}> $raw */
            $raw = Schema::getForeignKeys($table);

            foreach ($raw as $foreignKey) {
                $columns = array_values($foreignKey['columns']);
                $firstColumn = $columns[0] ?? '';

                $edges[] = new ErdEdge(
                    $table,
                    $foreignKey['foreign_table'],
                    $columns,
                    array_values($foreignKey['foreign_columns']),
                    ErdEdge::KIND_FK,
                    $tableInfo->isNullableColumn($firstColumn),
                    $tableInfo->isUniqueColumn($firstColumn),
                );
            }
        }

        usort($edges, static fn (ErdEdge $a, ErdEdge $b): int => [$a->fromTable, $a->toTable] <=> [$b->fromTable, $b->toTable]);

        return $edges;
    }
}
