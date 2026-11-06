<?php

namespace Modules\Core\Support\Erd;

/**
 * A discovered Eloquent model and the module directory it lives in.
 */
final readonly class ModelInfo
{
    public function __construct(
        public string $class,
        public string $module,
        public string $table,
    ) {}
}
