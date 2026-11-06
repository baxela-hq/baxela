<?php

namespace Modules\Core\Support\Erd;

/**
 * A single database column, as introspected from the schema.
 */
final readonly class ColumnInfo
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public bool $autoIncrement = false,
    ) {}

    /**
     * Mermaid erDiagram attribute type: base type name, parentheses stripped,
     * non-alphanumeric characters folded to underscores (e.g. "int unsigned"
     * becomes int_unsigned, "varchar(255)" becomes varchar).
     */
    public function mermaidType(): string
    {
        $base = strtolower(preg_replace('/\(.*$/D', '', $this->type) ?? $this->type);

        return preg_replace('/[^a-z0-9_]/', '_', $base) ?? $base;
    }
}
