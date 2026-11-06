<?php

namespace Modules\Core\Support\Erd;

/**
 * Builds the complete ErdDocument by merging the live schema with the
 * Eloquent relation scan:
 *   - database FK edges stay solid; relation edges are kept only when no FK
 *     covers the same (child table, column set -> parent table);
 *   - "unresolved" reference columns (foo_id with neither FK nor relation)
 *     are listed per page instead of guessed into edges;
 *   - cross-module edges of both kinds feed the module dependency graph.
 */
class ErdGenerator
{
    public function __construct(
        protected SchemaIntrospector $introspector,
        protected ModelRelationsScanner $scanner,
        protected ModuleTableResolver $resolver,
    ) {}

    public function generate(): ErdDocument
    {
        $snapshot = $this->introspector->snapshot();
        $scan = $this->scanner->scan();

        $modules = $this->resolver->resolve($scan->tableModules, array_keys($snapshot->tables));

        $tables = [];
        foreach ($snapshot->tables as $name => $table) {
            $tables[$name] = $table->withModule($modules[$name] ?? ModuleTableResolver::OTHER);
        }

        $edges = $this->reconcileEdges($snapshot->fkEdges, $scan->edges, $tables);

        return new ErdDocument(
            $tables,
            $edges,
            $this->morphNotes($scan->morphNotes, $tables),
            $this->unresolvedRefs($tables, $edges, $scan->morphNotes),
            $this->dependencies($edges, $tables),
            $scan->skips,
        );
    }

    /**
     * FK edges win; app edges survive only when nothing equivalent exists and
     * duplicates of an app edge (e.g. belongsTo + hasMany of the same
     * relation) collapse to one. Nullability and single-column uniqueness of
     * app edges are filled in from the introspected child table.
     *
     * @param  array<int, ErdEdge>  $fkEdges
     * @param  array<int, ErdEdge>  $appEdges
     * @param  array<string, TableInfo>  $tables
     * @return array<int, ErdEdge>
     */
    protected function reconcileEdges(array $fkEdges, array $appEdges, array $tables): array
    {
        $edges = $fkEdges;
        $seen = [];
        foreach ($edges as $edge) {
            $seen[$edge->signature()] = true;
        }

        foreach ($appEdges as $edge) {
            if (isset($seen[$edge->signature()])) {
                continue;
            }

            $child = $tables[$edge->fromTable] ?? null;
            $firstColumn = $edge->columns[0] ?? '';

            if ($child !== null && $firstColumn !== '') {
                $edge = new ErdEdge(
                    $edge->fromTable,
                    $edge->toTable,
                    $edge->columns,
                    $edge->foreignColumns,
                    $edge->kind,
                    $child->isNullableColumn($firstColumn),
                    $child->isUniqueColumn($firstColumn),
                );
            }

            $edges[] = $edge;
            $seen[$edge->signature()] = true;
        }

        usort($edges, static fn (ErdEdge $a, ErdEdge $b): int => [$a->fromTable, $a->toTable, implode(',', $a->columns)] <=> [$b->fromTable, $b->toTable, implode(',', $b->columns)]);

        return $edges;
    }

    /**
     * Keep morph notes for tables that exist in the introspected schema.
     *
     * @param  array<int, MorphNote>  $morphNotes
     * @param  array<string, TableInfo>  $tables
     * @return array<int, MorphNote>
     */
    protected function morphNotes(array $morphNotes, array $tables): array
    {
        return array_values(array_filter(
            $morphNotes,
            static fn (MorphNote $note): bool => isset($tables[$note->table]),
        ));
    }

    /**
     * Columns ending in "_id" that are neither primary key, FK column, app
     * relation column nor morph id — cross-module references resolved via
     * gateways at runtime. Target hints are inferred from table names
     * (base+"s"/"es" suffix match) and are NOT verified.
     *
     * @param  array<string, TableInfo>  $tables
     * @param  array<int, ErdEdge>  $edges
     * @param  array<int, MorphNote>  $morphNotes
     * @return array<string, string|null>
     */
    protected function unresolvedRefs(array $tables, array $edges, array $morphNotes): array
    {
        $covered = [];
        foreach ($edges as $edge) {
            foreach ($edge->columns as $column) {
                $covered[$edge->fromTable.'.'.$column] = true;
            }
        }

        foreach ($morphNotes as $note) {
            $covered[$note->table.'.'.$note->idColumn] = true;
        }

        $unresolved = [];
        foreach ($tables as $tableInfo) {
            foreach ($tableInfo->columns as $column) {
                $key = $tableInfo->table.'.'.$column->name;

                if (! str_ends_with($column->name, '_id')
                    || $tableInfo->isPrimaryKeyColumn($column->name)
                    || isset($covered[$key])
                    || $column->name === 'id') {
                    continue;
                }

                $unresolved[$key] = $this->inferTarget(substr($column->name, 0, -3), $tables);
            }
        }

        ksort($unresolved);

        return $unresolved;
    }

    /**
     * @param  array<string, TableInfo>  $tables
     * @return string|null candidate table(s), null when nothing plausible exists
     */
    protected function inferTarget(string $base, array $tables): ?string
    {
        $candidates = [];
        foreach (array_keys($tables) as $name) {
            if ($name === $base.'s' || $name === $base.'es' || str_ends_with($name, '_'.$base.'s') || str_ends_with($name, '_'.$base.'es')) {
                $candidates[] = $name;
            }
        }

        sort($candidates);

        return match (count($candidates)) {
            0 => null,
            1 => $candidates[0],
            default => implode(' / ', $candidates).' (ambiguous)',
        };
    }

    /**
     * Distinct [dependent module, dependency module] pairs from edges whose
     * endpoints live in different modules.
     *
     * @param  array<int, ErdEdge>  $edges
     * @param  array<string, TableInfo>  $tables
     * @return array<int, array{0: string, 1: string}>
     */
    protected function dependencies(array $edges, array $tables): array
    {
        $pairs = [];
        foreach ($edges as $edge) {
            $from = $tables[$edge->fromTable]->module ?? null;
            $to = $tables[$edge->toTable]->module ?? null;

            if ($from === null || $to === null || $from === $to) {
                continue;
            }

            $pairs[$from.' --> '.$to] = [$from, $to];
        }

        ksort($pairs);

        return array_values($pairs);
    }
}
