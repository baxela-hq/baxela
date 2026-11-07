<?php

namespace Modules\Content\Transfer;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use InvalidArgumentException;
use Modules\Content\Actions\Admin\Page\CreatePageAction;
use Modules\Content\Actions\Admin\Page\UpdatePageAction;
use Modules\Content\Actions\Admin\Post\CreatePostAction;
use Modules\Content\Actions\Admin\Post\UpdatePostAction;
use Modules\Content\Actions\Admin\PostCategory\CreatePostCategoryAction;
use Modules\Content\Actions\Admin\PostCategory\UpdatePostCategoryAction;
use Modules\Content\Models\Page;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Content\Schemas\Page\PageTranslationSchema;
use Modules\Content\Schemas\Post\PostProductSchema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostSeoTranslationSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Schemas\Language\LanguageSchema;
use Throwable;

/**
 * Content module's implementation of the data-transfer standard: pages,
 * the post-category tree (parents-first) and posts.
 *
 * Rows match existing records by their default-language slug and replay
 * through the admin Create/Update Actions, so translation handling, SEO
 * filtering and the category/product syncs behave exactly like a manual
 * save. Categories travel as source ids (remapped; unresolved rows fail),
 * products are validated through the Catalog gateway like the admin
 * request does — a file replayed into a different catalog fails those
 * rows instead of pointing at the wrong products.
 */
class ContentTransfer implements ModuleTransferInterface
{
    public const string SECTION_PAGES = 'pages';

    public const string SECTION_POST_CATEGORIES = 'post-categories';

    public const string SECTION_POSTS = 'posts';

    private const int SENTINEL_BASE = -1;

    /** Exported source id → local id (or dry-run sentinel). */
    private array $postCategoryIdBySource = [];

    private int $sentinel = self::SENTINEL_BASE;

    public function __construct(
        protected CreatePageAction $createPage,
        protected UpdatePageAction $updatePage,
        protected CreatePostCategoryAction $createPostCategory,
        protected UpdatePostCategoryAction $updatePostCategory,
        protected CreatePostAction $createPost,
        protected UpdatePostAction $updatePost,
        protected CoreGatewayInterface $coreGateway,
        protected CatalogGatewayInterface $catalogGateway,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_PAGES),
            TransferSection::full(self::SECTION_POST_CATEGORIES),
            TransferSection::full(self::SECTION_POSTS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_PAGES => $this->exportPages(),
            self::SECTION_POST_CATEGORIES => $this->exportPostCategories(),
            self::SECTION_POSTS => $this->exportPosts(),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_PAGES => $this->importPages($rows, $options),
            self::SECTION_POST_CATEGORIES => $this->importPostCategories($rows, $options),
            self::SECTION_POSTS => $this->importPosts($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportPages(): array
    {
        $codeByLanguageId = $this->codeByLanguageId();

        $rows = [];
        Page::query()
            ->with(PageSchema::RES_TRANSLATIONS)
            ->orderBy(PageSchema::ID)
            ->chunkById(200, function ($pages) use (&$rows, $codeByLanguageId): void {
                foreach ($pages as $page) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $page->getKey(),
                        self::KEY_PAYLOAD => [
                            PageSchema::STATUS => $page->{PageSchema::STATUS}->value,
                            PageSchema::RES_TRANSLATIONS => $this->exportTranslations(
                                $page->{PageSchema::RES_TRANSLATIONS},
                                PageTranslationSchema::LANGUAGE_ID,
                                [
                                    PageTranslationSchema::TITLE,
                                    PageTranslationSchema::SLUG,
                                    PageTranslationSchema::CONTENT,
                                    PageTranslationSchema::DESCRIPTION,
                                ],
                                $codeByLanguageId,
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportPostCategories(): array
    {
        $codeByLanguageId = $this->codeByLanguageId();

        $rows = [];
        PostCategory::query()
            ->with(PostCategorySchema::RES_TRANSLATIONS)
            ->orderBy(PostCategorySchema::ID)
            ->chunkById(500, function ($categories) use (&$rows, $codeByLanguageId): void {
                // Parents before children, so a sequential replay can
                // resolve the parent references.
                foreach ($this->byTreeDepth($categories) as $category) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $category->getKey(),
                        self::KEY_PAYLOAD => [
                            // Source-store parent id; remapped on import.
                            PostCategorySchema::PARENT_ID => $category->{PostCategorySchema::PARENT_ID} !== null
                                ? (int) $category->{PostCategorySchema::PARENT_ID}
                                : null,
                            PostCategorySchema::POSITION => $category->{PostCategorySchema::POSITION},
                            PostCategorySchema::RES_TRANSLATIONS => $this->exportTranslations(
                                $category->{PostCategorySchema::RES_TRANSLATIONS},
                                PostCategoryTranslationSchema::LANGUAGE_ID,
                                [
                                    PostCategoryTranslationSchema::TITLE,
                                    PostCategoryTranslationSchema::SLUG,
                                    PostCategoryTranslationSchema::DESCRIPTION,
                                ],
                                $codeByLanguageId,
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportPosts(): array
    {
        $codeByLanguageId = $this->codeByLanguageId();

        $rows = [];
        Post::query()
            ->with([
                PostSchema::RES_TRANSLATIONS,
                PostSchema::RES_SEO,
                PostSchema::RES_CATEGORIES,
            ])
            ->orderBy(PostSchema::ID)
            ->chunkById(200, function ($posts) use (&$rows, $codeByLanguageId): void {
                foreach ($posts as $post) {
                    $seo = [];
                    foreach ($post->{PostSchema::RES_SEO} as $seoRow) {
                        $code = $codeByLanguageId[(int) $seoRow->{PostSeoTranslationSchema::LANGUAGE_ID}] ?? null;
                        if ($code !== null) {
                            $seo[] = [
                                PostSeoTranslationSchema::COL_LANGUAGE => $code,
                                PostSeoTranslationSchema::META_TITLE => $seoRow->{PostSeoTranslationSchema::META_TITLE},
                                PostSeoTranslationSchema::META_DESCRIPTION => $seoRow->{PostSeoTranslationSchema::META_DESCRIPTION},
                                PostSeoTranslationSchema::OPEN_GRAPH_TITLE => $seoRow->{PostSeoTranslationSchema::OPEN_GRAPH_TITLE},
                                PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION => $seoRow->{PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION},
                            ];
                        }
                    }

                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $post->getKey(),
                        self::KEY_PAYLOAD => [
                            PostSchema::STATUS => $post->{PostSchema::STATUS}->value,
                            PostSchema::PUBLISHED_AT => $post->{PostSchema::PUBLISHED_AT}?->toIso8601String(),
                            PostSchema::IMAGE_MEDIA_ID => $post->{PostSchema::IMAGE_MEDIA_ID},
                            PostSchema::IMAGE_URL => $post->{PostSchema::IMAGE_URL},
                            PostSchema::RES_TRANSLATIONS => $this->exportTranslations(
                                $post->{PostSchema::RES_TRANSLATIONS},
                                PostTranslationSchema::LANGUAGE_ID,
                                [
                                    PostTranslationSchema::TITLE,
                                    PostTranslationSchema::SLUG,
                                    PostTranslationSchema::CONTENT,
                                    PostTranslationSchema::DESCRIPTION,
                                ],
                                $codeByLanguageId,
                            ),
                            PostSchema::RES_SEO => $seo,
                            // Source-store category/product ids.
                            PostSchema::RES_CATEGORIES => $post->{PostSchema::RES_CATEGORIES}
                                ->pluck(PostCategorySchema::ID)->map(fn ($id) => (int) $id)->values()->all(),
                            PostSchema::RES_PRODUCTS => $post->products()
                                ->pluck(PostProductSchema::PRODUCT_ID)->map(fn ($id) => (int) $id)->values()->all(),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importPages(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();
        $languageIdByCode = $this->languageIdByCode();
        $defaultLanguageId = $this->defaultLanguageId();

        foreach (array_values($rows) as $index => $row) {
            $label = self::SECTION_PAGES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, $this->pageRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $item = $this->resolveTranslations(
                    $payload,
                    PageSchema::RES_TRANSLATIONS,
                    PageTranslationSchema::class,
                    $languageIdByCode,
                    $result,
                    $label,
                );
                if ($item === null) {
                    continue;
                }

                $existing = $this->matchByDefaultSlug(
                    $item[PageSchema::RES_TRANSLATIONS],
                    PageTranslationSchema::TABLE,
                    PageTranslationSchema::PAGE_ID,
                    $defaultLanguageId,
                );

                if (! $this->checkSlugUniqueness(
                    PageTranslationSchema::TABLE,
                    PageTranslationSchema::PAGE_ID,
                    $existing?->getKey(),
                    $item[PageSchema::RES_TRANSLATIONS],
                    $result,
                    $label,
                )) {
                    continue;
                }

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updatePage->handle((string) $existing->getKey(), $item);
                    $result->recordUpdated();

                    continue;
                }

                $this->createPage->handle($item);
                $result->recordCreated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importPostCategories(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();
        $languageIdByCode = $this->languageIdByCode();
        $defaultLanguageId = $this->defaultLanguageId();

        foreach (array_values($rows) as $index => $row) {
            $label = self::SECTION_POST_CATEGORIES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            // Parents replay before children (export order); a parent id
            // that is not part of this file cannot resolve.
            $parentId = $payload[PostCategorySchema::PARENT_ID];
            if ($parentId !== null) {
                $remappedParent = is_numeric($parentId)
                    ? ($this->postCategoryIdBySource[(int) $parentId] ?? null)
                    : null;
                if ($remappedParent === null) {
                    $result->recordFailed($label.': unresolved_parent');

                    continue;
                }
                $payload[PostCategorySchema::PARENT_ID] = $remappedParent;
            }

            $validator = Validator::make($payload, $this->postCategoryRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $item = $this->resolveTranslations(
                    $payload,
                    PostCategorySchema::RES_TRANSLATIONS,
                    PostCategoryTranslationSchema::class,
                    $languageIdByCode,
                    $result,
                    $label,
                );
                if ($item === null) {
                    continue;
                }

                $existing = $this->matchByDefaultSlug(
                    $item[PostCategorySchema::RES_TRANSLATIONS],
                    PostCategoryTranslationSchema::TABLE,
                    PostCategoryTranslationSchema::POST_CATEGORY_ID,
                    $defaultLanguageId,
                );

                if (! $this->checkSlugUniqueness(
                    PostCategoryTranslationSchema::TABLE,
                    PostCategoryTranslationSchema::POST_CATEGORY_ID,
                    $existing?->getKey(),
                    $item[PostCategorySchema::RES_TRANSLATIONS],
                    $result,
                    $label,
                )) {
                    continue;
                }

                if ($options->dryRun) {
                    $this->postCategoryIdBySource[(int) $sourceId] = $existing?->getKey() !== null
                        ? (int) $existing->getKey()
                        : $this->nextSentinel();
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    // Register before the strategy check: children of a
                    // skipped parent still need the remap to resolve.
                    $this->postCategoryIdBySource[(int) $sourceId] = (int) $existing->getKey();

                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updatePostCategory->handle((string) $existing->getKey(), $item);
                    $result->recordUpdated();

                    continue;
                }

                $record = $this->createPostCategory->handle($item);
                $this->postCategoryIdBySource[(int) $sourceId] = (int) $record->getKey();
                $result->recordCreated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importPosts(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();
        $languageIdByCode = $this->languageIdByCode();
        $defaultLanguageId = $this->defaultLanguageId();

        foreach (array_values($rows) as $index => $row) {
            $label = self::SECTION_POSTS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            // Catalog selections are validated through the gateway, never
            // by touching Catalog's tables.
            foreach ($payload[PostSchema::RES_PRODUCTS] ?? [] as $productId) {
                if (! is_numeric($productId) || ! $this->catalogGateway->productExists((int) $productId)) {
                    $result->recordFailed($label.': unknown_product:'.
                        (is_numeric($productId) ? (int) $productId : (string) $productId));

                    continue 2;
                }
            }

            // Categories remap through this run's category section.
            $categoryIds = [];
            foreach ($payload[PostSchema::RES_CATEGORIES] ?? [] as $sourceCategoryId) {
                $localId = is_numeric($sourceCategoryId)
                    ? ($this->postCategoryIdBySource[(int) $sourceCategoryId] ?? null)
                    : null;
                if ($localId === null) {
                    $result->recordFailed($label.': unresolved_category');

                    continue 2;
                }
                $categoryIds[] = $localId;
            }

            $validator = Validator::make($payload, $this->postRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $item = $this->resolveTranslations(
                    $payload,
                    PostSchema::RES_TRANSLATIONS,
                    PostTranslationSchema::class,
                    $languageIdByCode,
                    $result,
                    $label,
                );
                if ($item === null) {
                    continue;
                }

                $item = $this->resolveSeoTranslations($item, $languageIdByCode, $result, $label);
                if ($item === null) {
                    continue;
                }

                $item[PostSchema::RES_CATEGORIES] = $categoryIds;

                $existing = $this->matchByDefaultSlug(
                    $item[PostSchema::RES_TRANSLATIONS],
                    PostTranslationSchema::TABLE,
                    PostTranslationSchema::POST_ID,
                    $defaultLanguageId,
                );

                if (! $this->checkSlugUniqueness(
                    PostTranslationSchema::TABLE,
                    PostTranslationSchema::POST_ID,
                    $existing?->getKey(),
                    $item[PostSchema::RES_TRANSLATIONS],
                    $result,
                    $label,
                )) {
                    continue;
                }

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updatePost->handle((string) $existing->getKey(), $item);
                    $result->recordUpdated();

                    continue;
                }

                $this->createPost->handle($item);
                $result->recordCreated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function pageRules(): array
    {
        return [
            PageSchema::STATUS => ['required', 'string', new Enum(PageStatusEnum::class)],
            PageSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            PageSchema::RES_TRANSLATIONS.'.*.'.PageTranslationSchema::COL_LANGUAGE => ['required', 'string', 'distinct', 'size:2'],
            PageSchema::RES_TRANSLATIONS.'.*.'.PageTranslationSchema::TITLE => ['required', 'string', 'max:255'],
            PageSchema::RES_TRANSLATIONS.'.*.'.PageTranslationSchema::SLUG => ['required', 'string', 'max:255'],
            PageSchema::RES_TRANSLATIONS.'.*.'.PageTranslationSchema::CONTENT => ['required', 'string'],
            PageSchema::RES_TRANSLATIONS.'.*.'.PageTranslationSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function postCategoryRules(): array
    {
        return [
            PostCategorySchema::PARENT_ID => ['nullable', 'integer'],
            PostCategorySchema::POSITION => ['nullable', 'numeric', 'max:255'],
            PostCategorySchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            PostCategorySchema::RES_TRANSLATIONS.'.*.'.PostCategoryTranslationSchema::COL_LANGUAGE => ['required', 'string', 'distinct', 'size:2'],
            PostCategorySchema::RES_TRANSLATIONS.'.*.'.PostCategoryTranslationSchema::TITLE => ['required', 'string', 'max:255'],
            PostCategorySchema::RES_TRANSLATIONS.'.*.'.PostCategoryTranslationSchema::SLUG => ['required', 'string', 'max:255'],
            PostCategorySchema::RES_TRANSLATIONS.'.*.'.PostCategoryTranslationSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function postRules(): array
    {
        return [
            PostSchema::STATUS => ['required', 'string', new Enum(PostStatusEnum::class)],
            PostSchema::PUBLISHED_AT => ['nullable', 'date'],
            PostSchema::IMAGE_MEDIA_ID => ['nullable', 'integer'],
            PostSchema::IMAGE_URL => ['nullable', 'string', 'max:500'],
            PostSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            PostSchema::RES_TRANSLATIONS.'.*.'.PostTranslationSchema::COL_LANGUAGE => ['required', 'string', 'distinct', 'size:2'],
            PostSchema::RES_TRANSLATIONS.'.*.'.PostTranslationSchema::TITLE => ['required', 'string', 'max:255'],
            PostSchema::RES_TRANSLATIONS.'.*.'.PostTranslationSchema::SLUG => ['required', 'string', 'max:255'],
            PostSchema::RES_TRANSLATIONS.'.*.'.PostTranslationSchema::CONTENT => ['required', 'string'],
            PostSchema::RES_TRANSLATIONS.'.*.'.PostTranslationSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],
            PostSchema::RES_SEO => ['nullable', 'array'],
            PostSchema::RES_SEO.'.*.'.PostSeoTranslationSchema::META_TITLE => ['nullable', 'string', 'max:255'],
            PostSchema::RES_SEO.'.*.'.PostSeoTranslationSchema::META_DESCRIPTION => ['nullable', 'string', 'max:255'],
            PostSchema::RES_SEO.'.*.'.PostSeoTranslationSchema::OPEN_GRAPH_TITLE => ['nullable', 'string', 'max:255'],
            PostSchema::RES_SEO.'.*.'.PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION => ['nullable', 'string', 'max:255'],
            PostSchema::RES_CATEGORIES => ['nullable', 'array'],
            PostSchema::RES_PRODUCTS => ['nullable', 'array', 'max:1000'],
        ];
    }

    /**
     * Slug uniqueness per language, checked directly against the
     * translations table — LanguageUniquePair reads its pair from HTTP
     * request input, which an import replay does not have. The matched
     * record's own translation rows are exempt.
     *
     * @param  list<array<string, mixed>>  $translations  Language ids already resolved
     */
    private function checkSlugUniqueness(
        string $translationTable,
        string $ownerKey,
        int|string|null $existingId,
        array $translations,
        ImportResult $result,
        string $label,
    ): bool {
        foreach ($translations as $translation) {
            $query = DB::table($translationTable)
                ->where(PageTranslationSchema::SLUG, $translation['slug'])
                ->where(PageTranslationSchema::LANGUAGE_ID, $translation['language_id']);

            if ($existingId !== null) {
                $query->where($ownerKey, '!=', $existingId);
            }

            if ($query->exists()) {
                $result->recordFailed($label.': slug_taken:'.$translation['slug']);

                return false;
            }
        }

        return true;
    }

    /**
     * A row matches an existing record by the default language's slug —
     * the same handle the storefront addresses it by.
     *
     * @param  list<array<string, mixed>>  $translations  Language ids already resolved
     */
    private function matchByDefaultSlug(
        array $translations,
        string $translationTable,
        string $ownerKey,
        int $defaultLanguageId,
    ): ?object {
        $slug = null;
        foreach ($translations as $translation) {
            if ($translation['language_id'] === $defaultLanguageId) {
                $slug = $translation['slug'] ?? null;
            }
        }
        if ($slug === null) {
            return null;
        }

        $ownerId = DB::table($translationTable)
            ->where('language_id', $defaultLanguageId)
            ->where('slug', $slug)
            ->value($ownerKey);

        return $ownerId === null ? null : match ($translationTable) {
            PageTranslationSchema::TABLE => Page::query()->findOrFail($ownerId),
            PostTranslationSchema::TABLE => Post::query()->findOrFail($ownerId),
            PostCategoryTranslationSchema::TABLE => PostCategory::query()->findOrFail($ownerId),
            default => null,
        };
    }

    /**
     * Replace language codes with ids in a translation array, keeping
     * every other key the payload carries.
     *
     * @param  array<string, mixed>  $payload
     * @param  class-string  $translationSchema
     * @param  array<string, int>  $languageIdByCode
     * @return array<string, mixed>|null
     */
    private function resolveTranslations(
        array $payload,
        string $translationsKey,
        string $translationSchema,
        array $languageIdByCode,
        ImportResult $result,
        string $label,
    ): ?array {
        $translations = [];
        foreach ($payload[$translationsKey] as $translation) {
            $languageId = $languageIdByCode[$translation[$translationSchema::COL_LANGUAGE]] ?? null;
            if ($languageId === null) {
                $result->recordFailed($label.': unresolved_language:'.$translation[$translationSchema::COL_LANGUAGE]);

                return null;
            }

            $row = [$translationSchema::LANGUAGE_ID => $languageId];
            foreach ($translation as $key => $value) {
                if ($key !== $translationSchema::COL_LANGUAGE) {
                    $row[$key] = $value;
                }
            }
            $translations[] = $row;
        }

        $payload[$translationsKey] = $translations;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $languageIdByCode
     * @return array<string, mixed>|null
     */
    private function resolveSeoTranslations(array $item, array $languageIdByCode, ImportResult $result, string $label): ?array
    {
        $seo = [];
        foreach ($item[PostSchema::RES_SEO] ?? [] as $row) {
            $languageId = $languageIdByCode[$row[PostSeoTranslationSchema::COL_LANGUAGE]] ?? null;
            if ($languageId === null) {
                $result->recordFailed($label.': unresolved_language:'.$row[PostSeoTranslationSchema::COL_LANGUAGE]);

                return null;
            }

            $seo[] = [
                PostSeoTranslationSchema::LANGUAGE_ID => $languageId,
                PostSeoTranslationSchema::META_TITLE => $row[PostSeoTranslationSchema::META_TITLE] ?? null,
                PostSeoTranslationSchema::META_DESCRIPTION => $row[PostSeoTranslationSchema::META_DESCRIPTION] ?? null,
                PostSeoTranslationSchema::OPEN_GRAPH_TITLE => $row[PostSeoTranslationSchema::OPEN_GRAPH_TITLE] ?? null,
                PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION => $row[PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION] ?? null,
            ];
        }

        $item[PostSchema::RES_SEO] = $seo;

        return $item;
    }

    /**
     * Rows of a loaded translations relation as `[{language: code, ...}]`.
     * Every translation schema shares the `language` wire key.
     *
     * @param  iterable<int, mixed>  $translations
     * @param  list<string>  $valueKeys
     * @param  array<int, string>  $codeByLanguageId
     * @return list<array<string, mixed>>
     */
    private function exportTranslations(
        iterable $translations,
        string $languageIdKey,
        array $valueKeys,
        array $codeByLanguageId,
    ): array {
        $exported = [];
        foreach ($translations as $translation) {
            $code = $codeByLanguageId[(int) $translation->{$languageIdKey}] ?? null;
            if ($code === null) {
                continue;
            }

            $row = ['language' => $code];
            foreach ($valueKeys as $key) {
                $row[$key] = $translation->{$key};
            }
            $exported[] = $row;
        }

        return $exported;
    }

    /**
     * Sort one chunk of category rows parents-first: repeated passes emit
     * rows whose parent has already been emitted.
     *
     * @param  Collection<int, PostCategory>  $categories
     * @return Collection<int, PostCategory>
     */
    private function byTreeDepth(Collection $categories): Collection
    {
        $ordered = collect();
        $remaining = $categories->values();
        $emitted = collect();

        while ($remaining->isNotEmpty()) {
            $ready = $remaining->filter(
                fn (PostCategory $category) => $category->{PostCategorySchema::PARENT_ID} === null
                    || $emitted->contains('id', $category->{PostCategorySchema::PARENT_ID}),
            );

            if ($ready->isEmpty()) {
                // A cycle or a dangling parent: emit the rest in id order;
                // the import fails those rows with unresolved_parent.
                return $ordered->merge($remaining->sortBy(PostCategorySchema::ID));
            }

            foreach ($ready->sortBy([PostCategorySchema::POSITION, PostCategorySchema::ID]) as $category) {
                $ordered->push($category);
                $emitted->push($category);
                $remaining = $remaining->reject(fn (PostCategory $row) => $row->id === $category->id);
            }
        }

        return $ordered;
    }

    /**
     * Count a row's would-be outcome for a dry run.
     */
    private function recordOutcome(ImportResult $result, ImportOptions $options, bool $exists): void
    {
        if (! $exists) {
            $result->recordCreated();
        } elseif ($options->strategy === TransferStrategyEnum::SKIP) {
            $result->recordSkipped();
        } else {
            $result->recordUpdated();
        }
    }

    /**
     * @return array<string, int>
     */
    private function languageIdByCode(): array
    {
        $codes = $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE)
            ->all();

        return $this->coreGateway->getLanguageIdsByCodes($codes);
    }

    /**
     * @return array<int, string>
     */
    private function codeByLanguageId(): array
    {
        $codeByLanguageId = [];
        foreach ($this->coreGateway->getActiveLanguages() as $language) {
            $codeByLanguageId[(int) $language->getKey()] = $language->{LanguageSchema::CODE};
        }

        return $codeByLanguageId;
    }

    private function defaultLanguageId(): int
    {
        $default = $this->coreGateway->getDefaultLanguage();

        return (int) ($default->id ?? $this->coreGateway->getActiveLanguages()->first()?->getKey());
    }

    private function nextSentinel(): int
    {
        return $this->sentinel--;
    }
}
