<?php

namespace Modules\Core\Support\Erd;

/**
 * A single database index (primary, unique or plain).
 */
final readonly class IndexInfo
{
    /**
     * @param  array<int, string>  $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $primary = false,
        public bool $unique = false,
    ) {}

    public function isComposite(): bool
    {
        return count($this->columns) > 1;
    }

    /**
     * Whether this index makes exactly this one column unique on its own —
     * only single-column unique indexes imply one-to-one relationships.
     */
    public function makesColumnUnique(string $column): bool
    {
        return $this->unique && ! $this->isComposite() && $this->columns === [$column];
    }
}
