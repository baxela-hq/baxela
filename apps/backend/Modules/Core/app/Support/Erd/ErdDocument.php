<?php

namespace Modules\Core\Support\Erd;

/**
 * The complete ERD model: tables, edges, morph notes, unresolved reference
 * columns and module dependencies, ready to be rendered by a formatter.
 */
final class ErdDocument
{
    /**
     * @param  array<string, TableInfo>  $tables  keyed by table name
     * @param  array<int, ErdEdge>  $edges
     * @param  array<int, MorphNote>  $morphNotes
     * @param  array<string, string|null>  $unresolvedRefs  "table.column" => inferred target hint (unverified)
     * @param  array<int, array{0: string, 1: string}>  $dependencies  [dependent module, dependency module]
     * @param  array<int, string>  $skipDiagnostics  scanner skips as "Class::method — reason"
     */
    public function __construct(
        public array $tables,
        public array $edges,
        public array $morphNotes,
        public array $unresolvedRefs,
        public array $dependencies,
        public array $skipDiagnostics = [],
    ) {}

    public function moduleOf(string $table): ?string
    {
        return $this->tables[$table]->module ?? null;
    }

    /**
     * @return array<int, string> distinct module names, sorted case-insensitively
     */
    public function modules(): array
    {
        $modules = array_values(array_unique(array_map(
            static fn (TableInfo $table): string => $table->module,
            $this->tables,
        )));
        sort($modules, SORT_STRING);

        return $modules;
    }

    /**
     * @return array<string, TableInfo> tables of one module, keyed by table name
     */
    public function tablesFor(string $module): array
    {
        return array_filter(
            $this->tables,
            static fn (TableInfo $table): bool => $table->module === $module,
        );
    }

    /**
     * Edges where either endpoint belongs to the given set of tables.
     *
     * @param  array<string, TableInfo>  $tables
     * @return array<int, ErdEdge>
     */
    public function edgesTouching(array $tables): array
    {
        return array_values(array_filter(
            $this->edges,
            static function (ErdEdge $edge) use ($tables): bool {
                return isset($tables[$edge->fromTable]) || isset($tables[$edge->toTable]);
            },
        ));
    }

    /**
     * Morph notes whose table belongs to the given set of tables.
     *
     * @param  array<string, TableInfo>  $tables
     * @return array<int, MorphNote>
     */
    public function morphNotesFor(array $tables): array
    {
        return array_values(array_filter(
            $this->morphNotes,
            static fn (MorphNote $note): bool => isset($tables[$note->table]),
        ));
    }

    /**
     * Unresolved reference entries for the given set of tables.
     *
     * @param  array<string, TableInfo>  $tables
     * @return array<string, string|null>
     */
    public function unresolvedFor(array $tables): array
    {
        return array_filter(
            $this->unresolvedRefs,
            static function (string $key) use ($tables): bool {
                [$table] = explode('.', $key, 2);

                return isset($tables[$table]);
            },
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Per-module edge counts, counting edges by their child (from) table.
     *
     * @return array{fk: int, app: int, unresolved: int}
     */
    public function countsFor(string $module): array
    {
        $fk = $app = 0;
        foreach ($this->edges as $edge) {
            if (($this->tables[$edge->fromTable]->module ?? null) !== $module) {
                continue;
            }

            $edge->kind === ErdEdge::KIND_FK ? $fk++ : $app++;
        }

        return ['fk' => $fk, 'app' => $app, 'unresolved' => count($this->unresolvedFor($this->tablesFor($module)))];
    }
}
