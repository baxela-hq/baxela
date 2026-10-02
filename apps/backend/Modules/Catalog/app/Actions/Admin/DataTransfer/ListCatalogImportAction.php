<?php

namespace Modules\Catalog\Actions\Admin\DataTransfer;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\CatalogImport;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\QueryBuilder;

class ListCatalogImportAction
{
    /**
     * Module JSON import history, latest first — the shared audit table
     * also holds the product CSV runs.
     */
    public function handle(): LengthAwarePaginator
    {
        return QueryBuilder::for(CatalogImport::class)
            ->where(CatalogImportSchema::ENTITY, CatalogImportEntityEnum::CATALOG->value)
            ->allowedSorts(CatalogImportSchema::ID)
            ->defaultSort('-'.CatalogImportSchema::ID)
            ->paginate(Pagination::perPage());
    }
}
