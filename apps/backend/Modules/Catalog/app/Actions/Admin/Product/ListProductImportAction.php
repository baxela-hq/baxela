<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\ProductImport;
use Modules\Catalog\Schemas\ProductImport\ProductImportSchema;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\QueryBuilder;

class ListProductImportAction
{
    /**
     * Import history, latest first.
     */
    public function handle(): LengthAwarePaginator
    {
        return QueryBuilder::for(ProductImport::class)
            ->allowedSorts(ProductImportSchema::ID)
            ->defaultSort('-'.ProductImportSchema::ID)
            ->paginate(Pagination::perPage());
    }
}
