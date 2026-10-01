<?php

namespace Modules\Catalog\Actions\Admin\ProductComment;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListProductCommentAction extends AbstractProductCommentAction
{
    public function handle(): LengthAwarePaginator
    {
        $comments = QueryBuilder::for(ProductComment::class)
            ->allowedFilters(
                AllowedFilter::exact(Schema::STATUS),
                AllowedFilter::exact(Schema::PRODUCT_ID),
            )
            ->allowedSorts(
                Schema::CREATED_AT,
                Schema::STATUS,
            )
            ->select([
                Schema::TABLE.'.'.Schema::ID,
                Schema::TABLE.'.'.Schema::PRODUCT_ID,
                Schema::TABLE.'.'.Schema::USER_ID,
                Schema::TABLE.'.'.Schema::PARENT_ID,
                Schema::TABLE.'.'.Schema::BODY,
                Schema::TABLE.'.'.Schema::STATUS,
                Schema::TABLE.'.'.Schema::CREATED_AT,
                Schema::TABLE.'.'.Schema::UPDATED_AT,
            ])
            ->with(Schema::RES_PRODUCT.'.'.ProductSchema::RES_TRANSLATIONS)
            ->orderBy(Schema::TABLE.'.'.Schema::ID, 'desc')
            ->paginate(Pagination::perPage());

        $this->enrichWithUserNames($comments->getCollection());

        return $comments;
    }
}
