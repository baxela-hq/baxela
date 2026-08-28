<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Repositories\Queries\ProductQuery;

class ListProductAction
{
    public function __construct(protected ProductQuery $query) {}

    public function handle(): LengthAwarePaginator|AbstractPaginator
    {
        return $this->query->paginate(10);
    }
}
