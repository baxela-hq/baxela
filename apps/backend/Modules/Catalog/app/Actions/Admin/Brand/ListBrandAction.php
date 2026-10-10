<?php

namespace Modules\Catalog\Actions\Admin\Brand;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema as BTSchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListBrandAction extends AbstractBrandAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = BrandSchema::TABLE.'.'.BrandSchema::ID;

        return QueryBuilder::for(Brand::class)
            ->allowedFilters(
                AllowedFilter::custom(BTSchema::TITLE, new TranslationTitleFilter),
            )
            ->allowedSorts(
                BrandSchema::ID,
                BrandSchema::POSITION,
            )
            ->select([
                $id,
                BrandSchema::POSITION,
                BrandSchema::IMAGE_MEDIA_ID,
                BrandSchema::IMAGE_URL,
                BrandSchema::TABLE.'.'.BrandSchema::CREATED_AT,
                BrandSchema::TABLE.'.'.BrandSchema::UPDATED_AT,
            ])
            ->with(
                BrandSchema::RES_TRANSLATIONS,
            )
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
