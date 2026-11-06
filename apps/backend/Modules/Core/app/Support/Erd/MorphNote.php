<?php

namespace Modules\Core\Support\Erd;

/**
 * A polymorphic relation, documented as a note instead of an edge.
 *
 * Targets are the known morph aliases when resolvable from the morph map
 * (or the declaring model for morphOne/morphMany), null when dynamic.
 */
final readonly class MorphNote
{
    /**
     * @param  array<int, string>|null  $targets
     */
    public function __construct(
        public string $table,
        public string $typeColumn,
        public string $idColumn,
        public ?array $targets = null,
    ) {}
}
