<?php

namespace Modules\Core\Support\Erd;

/**
 * A relationship edge between two tables.
 *
 * - kind "fk": enforced database foreign key (rendered solid)
 * - kind "app": Eloquent relation without a database FK (rendered dashed)
 *
 * The edge is child-first: fromTable holds the foreign key columns.
 * Columns are arrays so composite foreign keys stay one logical edge.
 */
final readonly class ErdEdge
{
    public const string KIND_FK = 'fk';

    public const string KIND_APP = 'app';

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $foreignColumns
     */
    public function __construct(
        public string $fromTable,
        public string $toTable,
        public array $columns,
        public array $foreignColumns,
        public string $kind,
        public bool $nullable = false,
        public bool $unique = false,
    ) {}

    /**
     * Two edges cover the same relationship when child, parent and the
     * referenced column set match — used to drop app-level edges that a
     * database foreign key already represents.
     */
    public function covers(ErdEdge $other): bool
    {
        return $this->fromTable === $other->fromTable
            && $this->toTable === $other->toTable
            && $this->sortedColumns() === $other->sortedColumns();
    }

    /**
     * @return array<int, string>
     */
    public function sortedColumns(): array
    {
        $columns = $this->columns;
        sort($columns);

        return $columns;
    }

    public function signature(): string
    {
        return $this->fromTable.'|'.$this->toTable.'|'.implode(',', $this->sortedColumns());
    }
}
