<?php

namespace Modules\Catalog\Support\ProductImport;

use RuntimeException;

/**
 * Thrown inside a group's transaction when option cells cannot be
 * resolved, so the partial auto-creates roll back while the collected
 * row errors bubble up to the importer.
 */
final class ProductImportOptionResolutionException extends RuntimeException
{
    /**
     * @param  array<int, array{row: int, messages: array<int, string>}>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('product import option resolution failed');
    }
}
