<?php

namespace Modules\Core\Support\Erd;

/**
 * Result of the Eloquent relation scan.
 */
final readonly class ScanResult
{
    /**
     * @param  array<int, ModelInfo>  $models
     * @param  array<int, ErdEdge>  $edges  app-level edges (no DB FK behind them yet unverified)
     * @param  array<int, MorphNote>  $morphNotes
     * @param  array<string, string>  $tableModules  table name => owning module
     * @param  array<int, string>  $skips  diagnostics as "Class::method — reason"
     */
    public function __construct(
        public array $models,
        public array $edges,
        public array $morphNotes,
        public array $tableModules,
        public array $skips,
    ) {}
}
