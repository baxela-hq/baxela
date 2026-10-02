<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\CatalogImport;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\QueryBuilder;

class ListProductImportAction
{
    /**
     * Product CSV import history, latest first — the shared audit table
     * also holds whole-module JSON runs.
     */
    public function handle(): LengthAwarePaginator
    {
        return QueryBuilder::for(CatalogImport::class)
            ->where(CatalogImportSchema::ENTITY, CatalogImportEntityEnum::PRODUCT->value)
            ->allowedSorts(CatalogImportSchema::ID)
            ->defaultSort('-'.CatalogImportSchema::ID)
            ->paginate(Pagination::perPage());
    }
}
