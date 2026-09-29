<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\ProductImport;
use Modules\Catalog\Schemas\ProductImport\ProductImportSchema;
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
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
