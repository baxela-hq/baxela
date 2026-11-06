<?php

namespace Modules\Core\Support\Erd;

/**
 * Result of the live schema introspection: every table with columns and
 * indexes, plus one edge per enforced foreign key.
 */
final readonly class SchemaSnapshot
{
    /**
     * @param  array<string, TableInfo>  $tables  keyed by table name (module left empty)
     * @param  array<int, ErdEdge>  $fkEdges
     */
    public function __construct(
        public array $tables,
        public array $fkEdges,
    ) {}
}
