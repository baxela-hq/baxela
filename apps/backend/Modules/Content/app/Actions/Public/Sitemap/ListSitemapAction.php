<?php

namespace Modules\Content\Actions\Public\Sitemap;

use Illuminate\Support\Facades\Cache;
use Modules\Content\Models\Page;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Content\Schemas\Page\PageTranslationSchema as PagTSchema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;

class ListSitemapAction
{
    public function __construct(
        protected Post $posts,
        protected PostCategory $postCategories,
        protected Page $pages,
    ) {}

    /**
     * Every publicly visible content entity as sitemap feed entries:
     * slugs per language plus updated_at for lastmod. Cached because
     * crawlers and the storefront's ISR both re-request this far more
     * often than the content changes.
     */
    public function handle(): array
    {
        return Cache::remember(
            'content.public.sitemap',
            now()->addSeconds((int) config('content.sitemap.cache_ttl', 3600)),
            function (): array {
                return [
                    'posts' => $this->posts
                        ->where(PostSchema::STATUS, PostStatusEnum::PUBLISHED)
                        // scheduled publishing: a future publish date keeps the post
                        // hidden until the moment passes — no scheduler involved
                        ->where(function ($query) {
                            $query
                                ->whereNull(PostSchema::PUBLISHED_AT)
                                ->orWhere(PostSchema::PUBLISHED_AT, '<=', now());
                        })
                        ->with([PostSchema::RES_TRANSLATIONS => fn ($translation) => $translation->select([
                            PTSchema::POST_ID,
                            PTSchema::LANGUAGE_ID,
                            PTSchema::SLUG,
                        ])])
                        ->orderBy(PostSchema::ID)
                        ->get([PostSchema::ID, PostSchema::UPDATED_AT]),

                    'post_categories' => $this->postCategories
                        ->with([PostCategorySchema::RES_TRANSLATIONS => fn ($translation) => $translation->select([
                            PCTSchema::POST_CATEGORY_ID,
                            PCTSchema::LANGUAGE_ID,
                            PCTSchema::SLUG,
                        ])])
                        ->orderBy(PostCategorySchema::ID)
                        ->get([PostCategorySchema::ID, PostCategorySchema::UPDATED_AT]),

                    'pages' => $this->pages
                        ->where(PageSchema::STATUS, PageStatusEnum::PUBLISHED)
                        ->with([PageSchema::RES_TRANSLATIONS => fn ($translation) => $translation->select([
                            PagTSchema::PAGE_ID,
                            PagTSchema::LANGUAGE_ID,
                            PagTSchema::SLUG,
                        ])])
                        ->orderBy(PageSchema::ID)
                        ->get([PageSchema::ID, PageSchema::UPDATED_AT]),
                ];
            }
        );
    }
}
