<?php

namespace Modules\Catalog\Actions\Admin\DataTransfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Catalog\Actions\Admin\Attribute\CreateAttributeAction;
use Modules\Catalog\Actions\Admin\Attribute\UpdateAttributeAction;
use Modules\Catalog\Actions\Admin\AttributeGroup\CreateAttributeGroupAction;
use Modules\Catalog\Actions\Admin\AttributeGroup\UpdateAttributeGroupAction;
use Modules\Catalog\Actions\Admin\AttributeValue\CreateAttributeValueAction;
use Modules\Catalog\Actions\Admin\AttributeValue\UpdateAttributeValueAction;
use Modules\Catalog\Actions\Admin\Category\CreateCategoryAction;
use Modules\Catalog\Actions\Admin\Category\UpdateCategoryAction;
use Modules\Catalog\Actions\Admin\Featured\UpdateFeaturedAction;
use Modules\Catalog\Actions\Admin\Option\CreateOptionAction;
use Modules\Catalog\Actions\Admin\Option\UpdateOptionAction;
use Modules\Catalog\Actions\Admin\OptionValue\CreateOptionValueAction;
use Modules\Catalog\Actions\Admin\OptionValue\UpdateOptionValueAction;
use Modules\Catalog\Actions\Admin\Product\CreateProductAction;
use Modules\Catalog\Actions\Admin\Product\UpdateProductAction;
use Modules\Catalog\Exceptions\Data\ImportFailedException;
use Modules\Catalog\Http\Requests\Admin\Attribute\AttributeRequest;
use Modules\Catalog\Http\Requests\Admin\AttributeGroup\AttributeGroupRequest;
use Modules\Catalog\Http\Requests\Admin\AttributeValue\AttributeValueRequest;
use Modules\Catalog\Http\Requests\Admin\Category\CategoryRequest;
use Modules\Catalog\Http\Requests\Admin\Option\OptionRequest;
use Modules\Catalog\Http\Requests\Admin\OptionValue\OptionValueRequest;
use Modules\Catalog\Http\Requests\Admin\Product\ProductRequest;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Models\CatalogImport;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupTranslationSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStatusEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStrategyEnum;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema as PAVSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;
use Modules\Catalog\Schemas\Variant\VariantSchema as VSchema;
use Modules\Catalog\Support\DataTransfer\CatalogTransferFile;
use Modules\Catalog\Support\DataTransfer\CatalogTransferFormat;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Media\DTOs\MediaDto;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Core\Utils\Auth;
use RuntimeException;
use Throwable;

/**
 * Replay a module export file: sections in dependency order, each row
 * remapped (source ids -> local ids), resolved (language codes -> ids),
 * validated with the shared payload rules and written through the very
 * actions the admin endpoints use — so invariants and events behave
 * exactly like a manual save. Rows match existing records by natural
 * key (code, default-language slug or title, default-variant SKU) and
 * follow the on_duplicate strategy; every run lands in catalog_imports
 * with per-section counts.
 */
class ImportCatalogDataAction
{
    /** Errors kept per run, mirroring the catalog_imports.errors cap. */
    private const int ERROR_CAP = 100;

    /** Dry-run placeholder ids for records that would be created. */
    private const int SENTINEL_BASE = -1;

    /** Internal payload key carrying a value row's resolved owner id. */
    private const string OWNER_KEY = '@owner';

    /** @var array<string, array<int, int>> entity => source id => local id */
    protected array $maps = [];

    /** @var array<int, array<string, mixed>> */
    protected array $errors = [];

    /** @var array<string, array<string, int>> entity => counters */
    protected array $sectionSummary = [];

    /** @var array<int, string> */
    protected array $warnings = [];

    /** @var array<string, bool> media id => exists locally */
    protected array $mediaCache = [];

    /** @var array<string, int> code => id of the active languages */
    protected array $languageIdByCode = [];

    protected int $defaultLanguageId;

    protected string $defaultLanguageCode;

    protected CatalogImportStrategyEnum $strategy;

    protected bool $dryRun = false;

    protected int $sentinel = self::SENTINEL_BASE;

    public function __construct(
        protected CoreGatewayInterface $coreGateway,
        protected MediaGatewayInterface $mediaGateway,
        protected CreateAttributeGroupAction $createAttributeGroup,
        protected UpdateAttributeGroupAction $updateAttributeGroup,
        protected CreateAttributeAction $createAttribute,
        protected UpdateAttributeAction $updateAttribute,
        protected CreateAttributeValueAction $createAttributeValue,
        protected UpdateAttributeValueAction $updateAttributeValue,
        protected CreateOptionAction $createOption,
        protected UpdateOptionAction $updateOption,
        protected CreateOptionValueAction $createOptionValue,
        protected UpdateOptionValueAction $updateOptionValue,
        protected CreateCategoryAction $createCategory,
        protected UpdateCategoryAction $updateCategory,
        protected CreateProductAction $createProduct,
        protected UpdateProductAction $updateProduct,
        protected UpdateFeaturedAction $updateFeatured,
    ) {}

    /**
     * @param  array<string, mixed>  $data  media_id, on_duplicate, dry_run
     * @return array<string, mixed> summary
     *
     * @throws ImportFailedException
     */
    public function handle(array $data): array
    {
        $startedAt = microtime(true);

        $this->reset($data);

        $media = $this->resolveJson((int) $data[CatalogImportSchema::REQ_MEDIA_ID]);

        try {
            $file = CatalogTransferFile::decode(CatalogTransferFile::readContent($media));
        } catch (RuntimeException $e) {
            throw new ImportFailedException(meta: ['reason' => $e->getMessage()]);
        }

        $sections = CatalogTransferFile::sections($file);
        $totalRows = CatalogTransferFile::totalRows($file);

        if ($totalRows > CatalogTransferFormat::ROW_CAP) {
            throw new ImportFailedException(meta: ['reason' => 'row_cap_exceeded']);
        }

        foreach ($sections as $entity => $rows) {
            $this->sectionSummary[$entity] = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

            foreach (array_values($rows) as $index => $row) {
                $this->runRow($entity, $index + 1, $row);
            }
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $summary = $this->rollup();

        $record = CatalogImport::query()->create([
            CatalogImportSchema::USER_ID => Auth::id(),
            CatalogImportSchema::MEDIA_ID => $media->id,
            CatalogImportSchema::FILENAME => $media->name.($media->extension ? '.'.$media->extension : ''),
            CatalogImportSchema::ENTITY => CatalogImportEntityEnum::CATALOG->value,
            CatalogImportSchema::STATUS => ($summary['failed_count'] > 0
                && $summary['created_count'] + $summary['updated_count'] + $summary['skipped_count'] === 0)
                ? CatalogImportStatusEnum::FAILED->value
                : CatalogImportStatusEnum::COMPLETED->value,
            CatalogImportSchema::STRATEGY => $this->strategy->value,
            CatalogImportSchema::DRY_RUN => $this->dryRun,
            CatalogImportSchema::TOTAL_ROWS => $totalRows,
            CatalogImportSchema::CREATED_COUNT => $summary['created_count'],
            CatalogImportSchema::UPDATED_COUNT => $summary['updated_count'],
            CatalogImportSchema::SKIPPED_COUNT => $summary['skipped_count'],
            CatalogImportSchema::FAILED_COUNT => $summary['failed_count'],
            CatalogImportSchema::DURATION_MS => $durationMs,
            CatalogImportSchema::ERRORS => array_slice($this->errors, 0, self::ERROR_CAP),
            CatalogImportSchema::SUMMARY => [
                'sections' => $this->sectionSummary,
                'warnings' => $this->warnings,
            ],
        ]);

        return [
            'id' => $record->getKey(),
            'status' => $record->{CatalogImportSchema::STATUS}->value,
            'strategy' => $this->strategy->value,
            'dry_run' => $this->dryRun,
            'total_rows' => $totalRows,
            'created_count' => $summary['created_count'],
            'updated_count' => $summary['updated_count'],
            'skipped_count' => $summary['skipped_count'],
            'failed_count' => $summary['failed_count'],
            'duration_ms' => $durationMs,
            'sections' => $this->sectionSummary,
            'warnings' => array_values(array_unique($this->warnings)),
            'errors' => array_slice($this->errors, 0, self::ERROR_CAP),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ImportFailedException
     */
    protected function reset(array $data): void
    {
        $this->maps = [];
        $this->errors = [];
        $this->sectionSummary = [];
        $this->warnings = [];
        $this->mediaCache = [];
        $this->sentinel = self::SENTINEL_BASE;
        $this->strategy = CatalogImportStrategyEnum::from($data[CatalogImportSchema::REQ_ON_DUPLICATE]);
        $this->dryRun = (bool) $data[CatalogImportSchema::REQ_DRY_RUN];

        $this->languageIdByCode = $this->coreGateway->getLanguageIdsByCodes(
            $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->all()
        );

        if ($this->languageIdByCode === []) {
            throw new ImportFailedException(meta: ['reason' => 'no_active_languages']);
        }

        $this->defaultLanguageCode = $this->coreGateway->getDefaultLanguage()?->code
            ?? array_key_first($this->languageIdByCode);
        $this->defaultLanguageId = $this->languageIdByCode[$this->defaultLanguageCode];
    }

    /**
     * @throws ImportFailedException
     */
    protected function resolveJson(int $mediaId): MediaDto
    {
        $media = $this->mediaGateway->findById($mediaId);

        if (! $media || mb_strtolower(trim((string) $media->extension)) !== 'json') {
            throw new ImportFailedException(meta: ['reason' => 'invalid_media']);
        }

        return $media;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function runRow(string $entity, int $rowNumber, array $row): void
    {
        $messages = [];

        $sourceId = (int) ($row[CatalogTransferFormat::KEY_SOURCE_ID] ?? 0);
        if ($sourceId <= 0) {
            $messages[] = 'The source_id field must be a positive integer.';
        }

        $payload = $row[CatalogTransferFormat::KEY_PAYLOAD] ?? null;
        if (! is_array($payload)) {
            $messages[] = 'The payload must be an object.';
            $payload = [];
        }

        if ($messages === []) {
            $payload = $this->prepare($entity, $row, $payload, $messages);
        }

        if ($messages === []) {
            $this->validateShape($entity, $payload, $messages);
        }

        $existingId = null;
        if ($messages === []) {
            $existingId = $this->matchExisting($entity, $payload);
            $this->checkUniqueness($entity, $payload, $existingId, $messages);
        }

        if ($messages !== []) {
            $this->fail($entity, $rowNumber, $messages);

            return;
        }

        if ($existingId !== null && $this->strategy === CatalogImportStrategyEnum::SKIP) {
            $this->register($entity, $sourceId, $existingId);
            $this->sectionSummary[$entity]['skipped']++;

            return;
        }

        if ($this->dryRun) {
            $existingId !== null
                ? $this->sectionSummary[$entity]['updated']++
                : $this->sectionSummary[$entity]['created']++;
            // A would-be-created record still resolves references for
            // rows after it — with a negative placeholder id.
            $this->register($entity, $sourceId, $existingId ?? $this->sentinel--);

            return;
        }

        try {
            $id = DB::transaction(function () use ($entity, $payload, $existingId): int {
                return $this->apply($entity, $payload, $existingId);
            });
        } catch (Throwable $e) {
            report($e);
            $this->fail($entity, $rowNumber, ['The row could not be imported.']);

            return;
        }

        $this->register($entity, $sourceId, $id);
        $existingId !== null
            ? $this->sectionSummary[$entity]['updated']++
            : $this->sectionSummary[$entity]['created']++;
    }

    /**
     * Remap inbound references and resolve language codes; fills the
     * nullable keys the actions read unconditionally.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     * @return array<string, mixed>
     */
    protected function prepare(string $entity, array $row, array $payload, array &$messages): array
    {
        return match ($entity) {
            CatalogTransferFormat::SECTION_ATTRIBUTE_GROUPS => $this->prepareWithTranslations($payload,
                AttributeGroupSchema::POSITION),
            CatalogTransferFormat::SECTION_ATTRIBUTES => $this->prepareAttribute($payload, $messages),
            CatalogTransferFormat::SECTION_ATTRIBUTE_VALUES => $this->prepareOwnedTranslations($payload,
                AttributeValueSchema::POSITION,
                CatalogTransferFormat::SECTION_ATTRIBUTES,
                $row, $messages),
            CatalogTransferFormat::SECTION_OPTIONS => $this->prepareWithTranslations($payload,
                OptionSchema::POSITION),
            CatalogTransferFormat::SECTION_OPTION_VALUES => $this->prepareOwnedTranslations($payload,
                OptionValueSchema::POSITION,
                CatalogTransferFormat::SECTION_OPTIONS,
                $row, $messages),
            CatalogTransferFormat::SECTION_CATEGORIES => $this->prepareCategory($payload, $messages),
            CatalogTransferFormat::SECTION_PRODUCTS => $this->prepareProduct($payload, $messages),
            CatalogTransferFormat::SECTION_FEATURED_ITEMS => $this->prepareFeatured($payload, $messages),
            default => $payload,
        };
    }

    /**
     * Remap the full-sync featured payload's source ids to local ids.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     * @return array<string, mixed>
     */
    protected function prepareFeatured(array $payload, array &$messages): array
    {
        $payload[FeaturedItemSchema::REQ_PRODUCT_IDS] = $this->localIds(
            CatalogTransferFormat::SECTION_PRODUCTS,
            (array) ($payload[FeaturedItemSchema::REQ_PRODUCT_IDS] ?? []),
            'product',
            $messages
        );
        $payload[FeaturedItemSchema::REQ_CATEGORY_IDS] = $this->localIds(
            CatalogTransferFormat::SECTION_CATEGORIES,
            (array) ($payload[FeaturedItemSchema::REQ_CATEGORY_IDS] ?? []),
            'category',
            $messages
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     * @return array<string, mixed>
     */
    protected function prepareAttribute(array $payload, array &$messages): array
    {
        $payload[AttributeSchema::GROUP_ID] = $this->localId(
            CatalogTransferFormat::SECTION_ATTRIBUTE_GROUPS,
            (int) ($payload[AttributeSchema::GROUP_ID] ?? 0),
            'attribute group',
            $messages
        );
        $payload[AttributeSchema::POSITION] ??= null;

        return $this->resolveLanguages($payload);
    }

    /**
     * Value rows (attribute-values, option-values) reference their owner
     * out of band — in the API the owner lives in the URL path.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     * @return array<string, mixed>
     */
    protected function prepareOwnedTranslations(
        array $payload,
        string $positionKey,
        string $ownerSection,
        array $row,
        array &$messages
    ): array {
        $payload[self::OWNER_KEY] = $this->localId(
            $ownerSection,
            (int) ($row[CatalogTransferFormat::KEY_OWNER] ?? 0),
            'owner',
            $messages
        );
        $payload[$positionKey] ??= null;

        return $this->resolveLanguages($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     * @return array<string, mixed>
     */
    protected function prepareCategory(array $payload, array &$messages): array
    {
        if (($payload[CategorySchema::PARENT_ID] ?? null) !== null) {
            $payload[CategorySchema::PARENT_ID] = $this->localId(
                CatalogTransferFormat::SECTION_CATEGORIES,
                (int) $payload[CategorySchema::PARENT_ID],
                'parent category',
                $messages
            );
        }

        $payload[CategorySchema::POSITION] ??= null;
        $payload[CategorySchema::IMAGE_MEDIA_ID] = $this->existingMediaId($payload[CategorySchema::IMAGE_MEDIA_ID] ?? null);
        $payload[CategorySchema::IMAGE_URL] ??= null;
        $payload[CategoryAttributeSchema::REQ_ATTRIBUTE_IDS] = $this->localIds(
            CatalogTransferFormat::SECTION_ATTRIBUTES,
            (array) ($payload[CategoryAttributeSchema::REQ_ATTRIBUTE_IDS] ?? []),
            'attribute',
            $messages
        );

        return $this->resolveLanguages($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     * @return array<string, mixed>
     */
    protected function prepareProduct(array $payload, array &$messages): array
    {
        $payload[ProductSchema::RES_CATEGORIES] = $this->localIds(
            CatalogTransferFormat::SECTION_CATEGORIES,
            (array) ($payload[ProductSchema::RES_CATEGORIES] ?? []),
            'category',
            $messages
        );

        // Media rows are module-external; missing media drops the image
        // with a warning rather than leaving a dangling reference.
        foreach ((array) ($payload[ProductSchema::RES_IMAGES] ?? []) as $index => $image) {
            $mediaId = $image[ImageSchema::MEDIA_ID] ?? null;
            if ($this->existingMediaId($mediaId) === null) {
                unset($payload[ProductSchema::RES_IMAGES][$index]);
            } else {
                $payload[ProductSchema::RES_IMAGES][$index][ImageSchema::MEDIA_ID] = (int) $mediaId;
            }
        }
        if (isset($payload[ProductSchema::RES_IMAGES])) {
            $payload[ProductSchema::RES_IMAGES] = array_values($payload[ProductSchema::RES_IMAGES]);
            if ($payload[ProductSchema::RES_IMAGES] === []) {
                unset($payload[ProductSchema::RES_IMAGES]);
            }
        }

        foreach ((array) ($payload[ProductSchema::RES_VARIANTS] ?? []) as $index => $variant) {
            if (! empty($variant[VSchema::REQ_OPTION_VALUE_IDS])) {
                $variant[VSchema::REQ_OPTION_VALUE_IDS] = $this->localIds(
                    CatalogTransferFormat::SECTION_OPTION_VALUES,
                    (array) $variant[VSchema::REQ_OPTION_VALUE_IDS],
                    'option value',
                    $messages
                );
            }
            $payload[ProductSchema::RES_VARIANTS][$index] = $variant;
        }

        foreach ((array) ($payload[PAVSchema::REQ_ATTRIBUTE_VALUES] ?? []) as $index => $attributeValue) {
            $attributeValue[PAVSchema::ATTRIBUTE_ID] = $this->localId(
                CatalogTransferFormat::SECTION_ATTRIBUTES,
                (int) ($attributeValue[PAVSchema::ATTRIBUTE_ID] ?? 0),
                'attribute',
                $messages
            );
            if (($attributeValue[PAVSchema::ATTRIBUTE_VALUE_ID] ?? null) !== null) {
                $attributeValue[PAVSchema::ATTRIBUTE_VALUE_ID] = $this->localId(
                    CatalogTransferFormat::SECTION_ATTRIBUTE_VALUES,
                    (int) $attributeValue[PAVSchema::ATTRIBUTE_VALUE_ID],
                    'attribute value',
                    $messages
                );
            }

            $this->checkAttributeValueType($attributeValue, $messages);
            $payload[PAVSchema::REQ_ATTRIBUTE_VALUES][$index] = $attributeValue;
        }

        return $this->resolveLanguages($this->resolveLanguages($payload, ProductSchema::RES_SEO));
    }

    /**
     * Plain translation-bearing rows (groups, options): default the
     * position and resolve language codes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function prepareWithTranslations(array $payload, string $positionKey): array
    {
        $payload[$positionKey] ??= null;

        return $this->resolveLanguages($payload);
    }

    /**
     * Resolve `language` codes to language_id on a translations-shaped
     * key.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function resolveLanguages(array $payload, string $key = 'translations'): array
    {
        if (! is_array($payload[$key] ?? null)) {
            return $payload;
        }

        foreach ($payload[$key] as $index => $item) {
            $code = (string) ($item['language'] ?? '');
            if (! isset($this->languageIdByCode[$code])) {
                $payload[$key][$index]['language_id'] = null;

                continue;
            }

            $payload[$key][$index]['language_id'] = $this->languageIdByCode[$code];
        }

        return $payload;
    }

    /**
     * Shape validation through the rules the HTTP requests share.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     */
    protected function validateShape(string $entity, array $payload, array &$messages): void
    {
        // Unresolvable language codes get an explicit reason before the
        // shape rules reject the resolved-away language_id.
        $this->collectUnknownLanguages($payload, 'translations', $messages);
        $this->collectUnknownLanguages($payload, ProductSchema::RES_SEO, $messages);

        $rules = match ($entity) {
            CatalogTransferFormat::SECTION_ATTRIBUTE_GROUPS => AttributeGroupRequest::rulesFor($this->languageIdByCode),
            CatalogTransferFormat::SECTION_ATTRIBUTES => AttributeRequest::rulesFor($this->languageIdByCode),
            CatalogTransferFormat::SECTION_ATTRIBUTE_VALUES => AttributeValueRequest::rulesFor($this->languageIdByCode),
            CatalogTransferFormat::SECTION_OPTIONS => OptionRequest::rulesFor($this->languageIdByCode),
            CatalogTransferFormat::SECTION_OPTION_VALUES => OptionValueRequest::rulesFor($this->languageIdByCode),
            CatalogTransferFormat::SECTION_CATEGORIES => CategoryRequest::rulesFor($this->languageIdByCode),
            CatalogTransferFormat::SECTION_PRODUCTS => ProductRequest::rulesFor(
                $this->languageIdByCode,
                ($payload[ProductSchema::TYPE] ?? '') === ProductTypeEnum::VARIABLE->value
            ),
            default => [],
        };

        $validator = Validator::make($this->publicPayload($payload), $rules);
        if ($validator->fails()) {
            $messages = array_merge($messages, array_values($validator->errors()->all()));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     */
    protected function collectUnknownLanguages(array $payload, string $key, array &$messages): void
    {
        foreach ((array) ($payload[$key] ?? []) as $item) {
            if (is_array($item) && array_key_exists('language', $item)
                && ($item['language_id'] ?? null) === null) {
                $messages[] = 'Unknown language code: '.(string) $item['language'].'.';
            }
        }
    }

    /**
     * Row-bound uniqueness the shape rules cannot express: codes, SKUs
     * and per-language slugs must not collide with a DIFFERENT record.
     * In-file duplicates surface here too on real runs, because earlier
     * rows commit before later ones validate.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     */
    protected function checkUniqueness(string $entity, array $payload, ?int $existingId, array &$messages): void
    {
        switch ($entity) {
            case CatalogTransferFormat::SECTION_ATTRIBUTES:
                $taken = Attribute::query()
                    ->where(AttributeSchema::CODE, $payload[AttributeSchema::CODE] ?? '')
                    ->when($existingId !== null, fn ($query) => $query->where(AttributeSchema::ID, '!=', $existingId))
                    ->exists();
                if ($taken) {
                    $messages[] = "The code {$payload[AttributeSchema::CODE]} is already in use by another attribute.";
                }

                return;
            case CatalogTransferFormat::SECTION_OPTIONS:
                $this->checkSlugUniqueness(OptionTranslationSchema::TABLE, OptionTranslationSchema::OPTION_ID,
                    $payload, $existingId, $messages);

                return;
            case CatalogTransferFormat::SECTION_OPTION_VALUES:
                $this->checkSlugUniqueness(OptionValueTranslationSchema::TABLE, OptionValueTranslationSchema::OPTION_VALUE_ID,
                    $payload, $existingId, $messages);

                return;
            case CatalogTransferFormat::SECTION_CATEGORIES:
                $this->checkSlugUniqueness(CategoryTranslationSchema::TABLE, CategoryTranslationSchema::CATEGORY_ID,
                    $payload, $existingId, $messages);

                return;
            case CatalogTransferFormat::SECTION_PRODUCTS:
                $skus = array_map(
                    fn ($variant) => (string) ($variant[VSchema::SKU] ?? ''),
                    (array) ($payload[ProductSchema::RES_VARIANTS] ?? [])
                );
                foreach (array_unique(array_filter($skus)) as $sku) {
                    $taken = Variant::query()
                        ->where(VSchema::SKU, $sku)
                        ->when($existingId !== null, fn ($query) => $query->where(VSchema::PRODUCT_ID, '!=', $existingId))
                        ->exists();
                    if ($taken) {
                        $messages[] = "The SKU {$sku} is already in use by another product.";
                    }
                }

                $this->checkSlugUniqueness(PTSchema::TABLE, PTSchema::PRODUCT_ID, $payload, $existingId, $messages);

                return;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $messages
     */
    protected function checkSlugUniqueness(string $table, string $entityColumn, array $payload, ?int $existingId, array &$messages): void
    {
        foreach ((array) ($payload['translations'] ?? []) as $translation) {
            $slug = (string) ($translation['slug'] ?? '');
            $languageId = (int) ($translation['language_id'] ?? 0);
            if ($slug === '' || $languageId === 0) {
                continue;
            }

            $taken = DB::table($table)
                ->where('slug', $slug)
                ->where('language_id', $languageId)
                ->when($existingId !== null, fn ($query) => $query->where($entityColumn, '!=', $existingId))
                ->exists();
            if ($taken) {
                $messages[] = "The slug {$slug} is already in use.";
            }
        }
    }

    /**
     * Each submitted value must fill the typed column matching the
     * attribute's data_type, and select values must belong to it —
     * mirroring ProductRequest::withValidator. Negative ids are dry-run
     * placeholders for records created earlier in the same file; they
     * are trusted because the attributes section validated before.
     *
     * @param  array<string, mixed>  $attributeValue
     * @param  array<int, string>  $messages
     */
    protected function checkAttributeValueType(array $attributeValue, array &$messages): void
    {
        $attributeId = (int) ($attributeValue[PAVSchema::ATTRIBUTE_ID] ?? 0);
        if ($attributeId < 0) {
            return;
        }

        $attribute = Attribute::query()->find($attributeId);
        if ($attribute === null) {
            $messages[] = 'The referenced attribute does not exist.';

            return;
        }

        $dataType = $attribute->{AttributeSchema::DATA_TYPE};
        $valueKey = match ($dataType) {
            AttributeTypeEnum::SELECT, AttributeTypeEnum::MULTISELECT => PAVSchema::ATTRIBUTE_VALUE_ID,
            AttributeTypeEnum::NUMBER => PAVSchema::NUMBER_VALUE,
            AttributeTypeEnum::BOOLEAN => PAVSchema::BOOLEAN_VALUE,
            AttributeTypeEnum::TEXT => PAVSchema::TEXT_VALUE,
        };

        if (($attributeValue[$valueKey] ?? null) === null) {
            $messages[] = "The value for attribute {$attribute->{AttributeSchema::CODE}} must fill {$valueKey} ({$dataType->value} data type).";

            return;
        }

        if (in_array($dataType, [AttributeTypeEnum::SELECT, AttributeTypeEnum::MULTISELECT], true)) {
            $belongsToAttribute = AttributeValue::query()
                ->where(AttributeValueSchema::ID, (int) $attributeValue[PAVSchema::ATTRIBUTE_VALUE_ID])
                ->where(AttributeValueSchema::ATTRIBUTE_ID, $attributeId)
                ->exists();

            if (! $belongsToAttribute) {
                $messages[] = "The selected value does not belong to attribute {$attribute->{AttributeSchema::CODE}}.";
            }
        }
    }

    /**
     * Match an existing local record by natural key.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function matchExisting(string $entity, array $payload): ?int
    {
        return match ($entity) {
            CatalogTransferFormat::SECTION_ATTRIBUTE_GROUPS => $this->matchByTranslation(
                AttributeGroupTranslationSchema::TABLE,
                AttributeGroupTranslationSchema::ATTRIBUTE_GROUP_ID,
                'title',
                $payload
            ),
            CatalogTransferFormat::SECTION_ATTRIBUTES => $this->idOrNull(
                Attribute::query()
                    ->where(AttributeSchema::CODE, $payload[AttributeSchema::CODE] ?? '')
                    ->value(AttributeSchema::ID)
            ),
            CatalogTransferFormat::SECTION_ATTRIBUTE_VALUES => $this->matchAttributeValue($payload),
            CatalogTransferFormat::SECTION_OPTIONS => $this->matchByTranslation(
                OptionTranslationSchema::TABLE,
                OptionTranslationSchema::OPTION_ID,
                'slug',
                $payload
            ),
            CatalogTransferFormat::SECTION_OPTION_VALUES => $this->matchOptionValue($payload),
            CatalogTransferFormat::SECTION_CATEGORIES => $this->matchByTranslation(
                CategoryTranslationSchema::TABLE,
                CategoryTranslationSchema::CATEGORY_ID,
                'slug',
                $payload
            ),
            CatalogTransferFormat::SECTION_PRODUCTS => $this->matchProduct($payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function matchProduct(array $payload): ?int
    {
        $variants = (array) ($payload[ProductSchema::RES_VARIANTS] ?? []);
        $default = collect($variants)->firstWhere(VSchema::IS_DEFAULT, true) ?? ($variants[0] ?? null);
        $sku = (string) ($default[VSchema::SKU] ?? '');

        if ($sku === '') {
            return null;
        }

        return $this->idOrNull(
            Variant::query()->where(VSchema::SKU, $sku)->value(VSchema::PRODUCT_ID)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function matchAttributeValue(array $payload): ?int
    {
        $ownerId = (int) ($payload[self::OWNER_KEY] ?? 0);
        if ($ownerId <= 0) {
            // Dry-run placeholder owner — nothing to match against yet.
            return null;
        }

        $title = $this->defaultTranslationValue($payload, 'title');
        if ($title === null) {
            return null;
        }

        return $this->idOrNull(
            DB::table(AttributeValueTranslationSchema::TABLE)
                ->join(
                    AttributeValueSchema::TABLE,
                    AttributeValueSchema::TABLE.'.'.AttributeValueSchema::ID,
                    '=',
                    AttributeValueTranslationSchema::TABLE.'.'.AttributeValueTranslationSchema::ATTRIBUTE_VALUE_ID
                )
                ->where(AttributeValueTranslationSchema::TABLE.'.language_id', $this->defaultLanguageId)
                ->where(AttributeValueTranslationSchema::TABLE.'.title', $title)
                ->where(AttributeValueSchema::TABLE.'.'.AttributeValueSchema::ATTRIBUTE_ID, $ownerId)
                ->value(AttributeValueSchema::TABLE.'.'.AttributeValueSchema::ID)
        );
    }

    /**
     * Option values match by slug alone (slugs are unique per language
     * across the table), but a slug found under a different option is a
     * conflict, not a match.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function matchOptionValue(array $payload): ?int
    {
        $slug = $this->defaultTranslationValue($payload, 'slug');
        if ($slug === null) {
            return null;
        }

        $match = DB::table(OptionValueTranslationSchema::TABLE)
            ->where('language_id', $this->defaultLanguageId)
            ->where('slug', $slug)
            ->first();

        if ($match === null) {
            return null;
        }

        return (int) $match->{OptionValueTranslationSchema::OPTION_VALUE_ID};
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function matchByTranslation(string $table, string $entityColumn, string $field, array $payload): ?int
    {
        $value = $this->defaultTranslationValue($payload, $field);
        if ($value === null) {
            return null;
        }

        return $this->idOrNull(
            DB::table($table)
                ->where('language_id', $this->defaultLanguageId)
                ->where($field, $value)
                ->value($entityColumn)
        );
    }

    /**
     * Persist a row through the entity's create/update action.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function apply(string $entity, array $payload, ?int $existingId): int
    {
        $ownerId = (int) ($payload[self::OWNER_KEY] ?? 0);
        $data = $this->publicPayload($payload);

        return match ($entity) {
            CatalogTransferFormat::SECTION_ATTRIBUTE_GROUPS => (int) ($existingId !== null
                ? $this->updateAttributeGroup->handle((string) $existingId, $data)->getKey()
                : $this->createAttributeGroup->handle($data)->getKey()),
            CatalogTransferFormat::SECTION_ATTRIBUTES => (int) ($existingId !== null
                ? $this->updateAttribute->handle((string) $existingId, $data)->getKey()
                : $this->createAttribute->handle($data)->getKey()),
            CatalogTransferFormat::SECTION_ATTRIBUTE_VALUES => (int) ($existingId !== null
                ? $this->updateAttributeValue->handle((string) $ownerId, (string) $existingId, $data)->getKey()
                : $this->createAttributeValue->handle((string) $ownerId, $data)->getKey()),
            CatalogTransferFormat::SECTION_OPTIONS => (int) ($existingId !== null
                ? $this->updateOption->handle((string) $existingId, $data)->getKey()
                : $this->createOption->handle($data)->getKey()),
            CatalogTransferFormat::SECTION_OPTION_VALUES => (int) ($existingId !== null
                ? $this->updateOptionValue->handle((string) $ownerId, (string) $existingId, $data)->getKey()
                : $this->createOptionValue->handle((string) $ownerId, $data)->getKey()),
            CatalogTransferFormat::SECTION_CATEGORIES => (int) ($existingId !== null
                ? $this->updateCategory->handle((string) $existingId, $data)->getKey()
                : $this->createCategory->handle($data)->getKey()),
            CatalogTransferFormat::SECTION_PRODUCTS => (int) ($existingId !== null
                ? $this->updateProduct->handle((string) $existingId, $data)->getKey()
                : $this->createProduct->handle($data)->getKey()),
            // Full-sync section: one row replaces the whole featured
            // table, mirroring the admin endpoint. Returns a placeholder
            // id — no later section references featured rows.
            CatalogTransferFormat::SECTION_FEATURED_ITEMS => $this->updateFeatured->handle(
                array_map('intval', (array) ($data[FeaturedItemSchema::REQ_PRODUCT_IDS] ?? [])),
                array_map('intval', (array) ($data[FeaturedItemSchema::REQ_CATEGORY_IDS] ?? [])),
            ) ? 1 : 0,
            default => 0,
        };
    }

    /**
     * Drop the importer's internal bookkeeping key so the payload is
     * exactly request-shaped before validation or actions see it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function publicPayload(array $payload): array
    {
        unset($payload[self::OWNER_KEY]);

        return $payload;
    }

    /**
     * Resolve a source id to the local id of a record created or matched
     * earlier in this run.
     *
     * @param  array<int, string>  $messages
     */
    protected function localId(string $entity, int $sourceId, string $label, array &$messages): int
    {
        if ($sourceId <= 0) {
            $messages[] = "The referenced {$label} id must be a positive integer.";

            return 0;
        }

        $localId = $this->maps[$entity][$sourceId] ?? null;
        if ($localId === null) {
            $messages[] = "Unknown {$label} source id: {$sourceId}.";

            return 0;
        }

        return $localId;
    }

    /**
     * @param  array<int, mixed>  $sourceIds
     * @param  array<int, string>  $messages
     * @return array<int, int>
     */
    protected function localIds(string $entity, array $sourceIds, string $label, array &$messages): array
    {
        $localIds = [];
        foreach ($sourceIds as $sourceId) {
            $localIds[] = $this->localId($entity, (int) $sourceId, $label, $messages);
        }

        // Keep dry-run placeholders (negative); drop only failed lookups.
        return array_values(array_filter($localIds, fn ($id) => $id !== 0));
    }

    /**
     * Media rows live in another module; ids only port within a store.
     * Missing media drops the reference with a warning.
     */
    protected function existingMediaId(int|string|null $mediaId): ?int
    {
        if ($mediaId === null || $mediaId === '' || (int) $mediaId === 0) {
            return null;
        }

        $key = (string) (int) $mediaId;
        if (! array_key_exists($key, $this->mediaCache)) {
            $this->mediaCache[$key] = $this->mediaGateway->findById($key) !== null;
        }

        if (! $this->mediaCache[$key]) {
            $this->warnings[] = 'media_missing:'.$key;

            return null;
        }

        return (int) $mediaId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function defaultTranslationValue(array $payload, string $field): ?string
    {
        foreach ((array) ($payload['translations'] ?? []) as $translation) {
            if (($translation['language'] ?? null) === $this->defaultLanguageCode
                && is_string($translation[$field] ?? null)
                && $translation[$field] !== '') {
                return $translation[$field];
            }
        }

        return null;
    }

    protected function register(string $entity, int $sourceId, int $localId): void
    {
        if ($sourceId > 0) {
            $this->maps[$entity][$sourceId] = $localId;
        }
    }

    /**
     * @param  array<int, string>  $messages
     */
    protected function fail(string $entity, int $rowNumber, array $messages): void
    {
        $this->sectionSummary[$entity]['failed']++;
        $this->errors[] = ['section' => $entity, 'row' => $rowNumber, 'messages' => $messages];
    }

    /**
     * @return array<string, int>
     */
    protected function rollup(): array
    {
        $created = $updated = $skipped = $failed = 0;
        foreach ($this->sectionSummary as $counters) {
            $created += $counters['created'];
            $updated += $counters['updated'];
            $skipped += $counters['skipped'];
            $failed += $counters['failed'];
        }

        return [
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
        ];
    }

    protected function idOrNull(mixed $id): ?int
    {
        return $id === null ? null : (int) $id;
    }
}
