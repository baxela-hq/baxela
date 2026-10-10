<?php

namespace Modules\Catalog\Actions\Public\Sitemap;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;

class ListSitemapAction
{
    public function __construct(protected Product $model) {}

    /**
     * Every publicly visible product as a sitemap feed entry: the slugs
     * per language plus updated_at for lastmod — everything a storefront
     * needs to emit locale-aware <url> rows. Cached because crawlers and
     * the storefront's ISR both re-request this far more often than the
     * catalog changes.
     */
    public function handle(): Collection
    {
        return Cache::remember(
            'catalog.public.sitemap',
            now()->addSeconds((int) config('catalog.sitemap.cache_ttl', 3600)),
            fn () => $this->model
                ->where(ProductSchema::STATUS, ProductStatusEnum::IN_STOCK)
                ->where(ProductSchema::IS_PUBLISHED, true)
                ->with([ProductSchema::RES_TRANSLATIONS => fn ($query) => $query->select([
                    PTSchema::PRODUCT_ID,
                    PTSchema::LANGUAGE_ID,
                    PTSchema::SLUG,
                ])])
                ->orderBy(ProductSchema::ID)
                ->get([ProductSchema::ID, ProductSchema::UPDATED_AT])
        );
    }
}
