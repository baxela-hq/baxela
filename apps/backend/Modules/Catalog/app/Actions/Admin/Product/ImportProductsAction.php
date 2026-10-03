<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Modules\Catalog\Exceptions\Product\ImportFailedException;
use Modules\Catalog\Models\CatalogImport;
use Modules\Catalog\Models\CategoryTranslation;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStatusEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStrategyEnum;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Support\ProductImport\ProductImportCsv;
use Modules\Catalog\Support\ProductImport\ProductImportFields;
use Modules\Catalog\Support\ProductImport\ProductImportOptionResolutionException;
use Modules\Catalog\Support\ProductImport\ProductImportOptionResolver;
use Modules\Catalog\Support\ProductImport\ProductImportSlug;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Media\DTOs\MediaDto;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Core\Utils\Auth;
use RuntimeException;
use Throwable;

class ImportProductsAction
{
    /** Errors kept per run, mirroring the catalog_imports.errors cap. */
    private const int ERROR_CAP = 100;

    public function __construct(
        protected MediaGatewayInterface $mediaGateway,
        protected CoreGatewayInterface $coreGateway,
        protected CreateProductAction $createAction,
        protected UpdateProductAction $updateAction,
    ) {}

    /**
     * Import (or dry-run) the mapped CSV. Rows sharing a handle become
     * the variants of one product; handle-less rows stand alone. Every
     * product runs through the same actions the product form uses, in
     * its own transaction, so valid products commit while failures are
     * collected with their file line numbers. The run is always
     * recorded in catalog_imports.
     *
     * @param  array<string, mixed>  $data  media_id, mapping, on_duplicate, dry_run, create_missing_options
     * @return array<string, mixed> summary
     *
     * @throws ImportFailedException
     */
    public function handle(array $data): array
    {
        $startedAt = microtime(true);

        $media = $this->resolveCsv((int) $data[CatalogImportSchema::REQ_MEDIA_ID]);
        [$languageCodes, $defaultCode] = [$this->languageCodes(), $this->defaultLanguageCode()];

        try {
            $content = ProductImportCsv::readContent($media);
        } catch (RuntimeException) {
            throw new ImportFailedException(meta: ['reason' => 'unreadable_file']);
        }

        [, $rows] = ProductImportCsv::parse($content);

        if ($rows === []) {
            throw new ImportFailedException(meta: ['reason' => 'empty_file']);
        }

        $header = array_shift($rows);

        if (count($rows) > ProductImportCsv::ROW_CAP) {
            throw new ImportFailedException(meta: ['reason' => 'row_cap_exceeded']);
        }

        $mapping = $data[CatalogImportSchema::REQ_MAPPING];
        $strategy = CatalogImportStrategyEnum::from($data[CatalogImportSchema::REQ_ON_DUPLICATE]);
        $dryRun = (bool) $data[CatalogImportSchema::REQ_DRY_RUN];

        // Position => mapped field key; unmapped positions stay null.
        $positionFields = [];
        foreach ($header as $index => $column) {
            $positionFields[$index] = $mapping[$column] ?? null;
        }
        $mappedFields = array_values(array_filter($positionFields));

        $languageIdByCode = $this->coreGateway->getLanguageIdsByCodes($languageCodes);
        $categoryIdBySlug = CategoryTranslation::query()
            ->pluck(CategoryTranslationSchema::CATEGORY_ID, CategoryTranslationSchema::SLUG)
            ->all();

        $skus = [];
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                if (($positionFields[$index] ?? null) === ProductImportFields::FIELD_SKU) {
                    $skus[] = trim((string) $cell);
                }
            }
        }
        $existingProductBySku = Variant::query()
            ->whereIn(VariantSchema::SKU, array_values(array_filter($skus)))
            ->pluck(VariantSchema::PRODUCT_ID, VariantSchema::SKU)
            ->all();

        $rules = $this->rowRules($languageCodes, $defaultCode);
        // Product cells of a group come from its first row, so later
        // rows may leave the default language's title empty.
        $laterRowRules = $rules;
        $laterRowRules[ProductImportFields::TITLE_PREFIX.$defaultCode] = ['nullable', 'string', 'max:255'];
        $createMissing = (bool) $data[CatalogImportSchema::REQ_CREATE_MISSING_OPTIONS];

        // Pass 1: map cells to field values and collect the row-local
        // messages (published vocabulary, in-file SKUs). Shape rules
        // run per group, where a row's position is known.
        $rowData = [];
        $seenSkus = [];
        foreach (array_values($rows) as $rowIndex => $cells) {
            $line = $rowIndex + 2; // +1 for the header, +1 for 1-based lines
            $messages = [];

            $values = [];
            foreach ($cells as $index => $cell) {
                $field = $positionFields[$index] ?? null;
                if ($field !== null) {
                    $values[$field] = trim((string) $cell);
                }
            }
            foreach ($mappedFields as $field) {
                $values[$field] = $values[$field] ?? '';
            }

            $published = (string) ($values[ProductImportFields::FIELD_IS_PUBLISHED] ?? '');
            if ($published !== ''
                && ! in_array(mb_strtolower($published), ProductImportFields::publishedVocabulary(), true)) {
                $messages[] = 'The is_published field must be one of: 1, 0, true, false, yes, no.';
            }

            $sku = (string) ($values[ProductImportFields::FIELD_SKU] ?? '');
            if ($sku !== '' && isset($seenSkus[$sku])) {
                $messages[] = "Duplicate SKU in file (first seen on line {$seenSkus[$sku]}).";
            } elseif ($sku !== '') {
                $seenSkus[$sku] = $line;
            }

            $rowData[] = ['line' => $line, 'values' => $values, 'messages' => $messages];
        }

        // Pass 2: rows sharing a handle are the variants of one product;
        // handle-less rows stand alone exactly like the single-variant
        // import always did.
        $groups = [];
        foreach ($rowData as $row) {
            $handle = trim((string) ($row['values'][ProductImportFields::FIELD_HANDLE] ?? ''));
            $key = $handle !== '' ? 'h:'.$handle : 'r:'.$row['line'];
            $groups[$key]['rows'][] = $row;
        }

        $errors = [];
        $usedSlugsByCode = [];
        $resolver = null;
        $created = $updated = $skipped = $failed = 0;

        foreach ($groups as $group) {
            $groupRows = $group['rows'];
            $first = $groupRows[0];

            // Option cells: each slot is a name/value pair that must be
            // filled together, and slots are used in order.
            $pairsByRow = [];
            foreach ($groupRows as $i => $row) {
                $validator = $this->validateRow($row['values'], $i === 0 ? $rules : $laterRowRules);
                if ($validator->fails()) {
                    $groupRows[$i]['messages'] = array_merge(
                        array_values($validator->errors()->all()),
                        $row['messages'],
                    );
                }

                $pairs = [];
                for ($slot = 1; $slot <= ProductImportFields::OPTION_SLOTS; $slot++) {
                    $name = (string) ($row['values'][ProductImportFields::optionNameField($slot)] ?? '');
                    $value = (string) ($row['values'][ProductImportFields::optionValueField($slot)] ?? '');

                    if ($name === '' && $value === '') {
                        continue;
                    }

                    if ($value === '') {
                        $groupRows[$i]['messages'][] = sprintf(
                            'The option%d value must be filled when option%d name is set.', $slot, $slot);

                        continue;
                    }
                    if ($name === '') {
                        $groupRows[$i]['messages'][] = sprintf(
                            'The option%d name must be filled when option%d value is set.', $slot, $slot);

                        continue;
                    }
                    if ($slot > 1 && $pairs === []) {
                        $groupRows[$i]['messages'][] = sprintf(
                            'The option%d fields cannot be used before option1 is filled.', $slot);

                        continue;
                    }

                    $pairs[] = [$name, $value];
                }
                $pairsByRow[] = $pairs;
            }

            $isVariable = array_filter($pairsByRow) !== [];
            if ($isVariable) {
                foreach ($groupRows as $i => $row) {
                    if ($pairsByRow[$i] === []) {
                        $groupRows[$i]['messages'][] =
                            'Every variant row of a multi-variant product needs at least one option value.';
                    }
                }
            }

            // A product is all-or-nothing: any invalid row fails the
            // whole group, every offending row listed with its line.
            $invalidRows = array_filter($groupRows, fn (array $row) => $row['messages'] !== []);
            if ($invalidRows !== []) {
                $failed++;
                foreach ($invalidRows as $row) {
                    $errors[] = ['row' => $row['line'], 'messages' => $row['messages']];
                }

                continue;
            }

            // Match existing products by any of the group's SKUs; SKUs
            // spanning several products cannot be merged into one.
            $existingProductIds = [];
            foreach ($groupRows as $row) {
                $sku = (string) $row['values'][ProductImportFields::FIELD_SKU];
                if ($sku !== '' && isset($existingProductBySku[$sku])) {
                    $existingProductIds[(int) $existingProductBySku[$sku]] = true;
                }
            }
            if (count($existingProductIds) > 1) {
                $failed++;
                $errors[] = ['row' => $first['line'], 'messages' => [
                    'The rows of this product reference SKUs that belong to different products.',
                ]];

                continue;
            }
            $existingProductId = $existingProductIds === [] ? null : array_key_first($existingProductIds);

            if ($existingProductId !== null && $strategy === CatalogImportStrategyEnum::SKIP) {
                $skipped++;

                continue;
            }

            [$categoryIds, $categoryMessages] = $this->resolveCategories(
                (string) ($first['values'][ProductImportFields::FIELD_CATEGORIES] ?? ''),
                $categoryIdBySlug,
            );
            if ($categoryMessages !== []) {
                $failed++;
                $errors[] = ['row' => $first['line'], 'messages' => $categoryMessages];

                continue;
            }

            // Product-level cells come from the first row of the group.
            $published = (string) ($first['values'][ProductImportFields::FIELD_IS_PUBLISHED] ?? '');
            $translations = $this->buildTranslations(
                $first['values'],
                (string) $first['values'][ProductImportFields::FIELD_SKU],
                $languageCodes,
                $languageIdByCode,
                $existingProductId,
                $usedSlugsByCode,
            );

            $variants = [];
            foreach ($groupRows as $i => $row) {
                $variant = [
                    VariantSchema::SKU => (string) $row['values'][ProductImportFields::FIELD_SKU],
                    VariantSchema::PRICE => $row['values'][ProductImportFields::FIELD_PRICE],
                    VariantSchema::QUANTITY => (int) (($row['values'][ProductImportFields::FIELD_QUANTITY] ?? '') ?: 0),
                    VariantSchema::IS_DEFAULT => $i === 0,
                ];
                foreach ([
                    VariantSchema::BARCODE => ProductImportFields::FIELD_BARCODE,
                    VariantSchema::COMPARE_PRICE => ProductImportFields::FIELD_COMPARE_PRICE,
                    VariantSchema::COST_PRICE => ProductImportFields::FIELD_COST_PRICE,
                ] as $column => $field) {
                    if (($row['values'][$field] ?? '') !== '') {
                        $variant[$column] = $row['values'][$field];
                    }
                }

                $variants[] = $variant;
            }

            // Strict runs resolve (and report) unknown options in dry
            // runs too; auto-creating runs only resolve while writing,
            // since a dry run must not create anything.
            $resolveStrictly = $isVariable && ! $createMissing;
            $resolveOnWrite = $isVariable && $createMissing && ! $dryRun;

            if ($resolveStrictly) {
                $resolver ??= new ProductImportOptionResolver(false, (int) $languageIdByCode[$defaultCode]);
                $resolutionErrors = $this->attachOptionValueIds($groupRows, $pairsByRow, $variants, $resolver);
                if ($resolutionErrors !== []) {
                    $failed++;
                    $errors = array_merge($errors, $resolutionErrors);

                    continue;
                }
            }

            if (! $dryRun) {
                if ($resolveOnWrite) {
                    $resolver ??= new ProductImportOptionResolver(true, (int) $languageIdByCode[$defaultCode]);
                    $resolver->mark();
                }

                try {
                    DB::transaction(function () use (
                        $resolveOnWrite, $resolver, $groupRows, $pairsByRow, $variants,
                        $isVariable, $published, $categoryIds, $translations, $existingProductId, $first,
                    ): void {
                        if ($resolveOnWrite) {
                            $resolutionErrors = $this->attachOptionValueIds($groupRows, $pairsByRow, $variants, $resolver);
                            if ($resolutionErrors !== []) {
                                throw new ProductImportOptionResolutionException($resolutionErrors);
                            }
                        }

                        $payload = $this->buildPayload($isVariable, $first['values'], $published, $categoryIds, $translations, $variants);

                        if ($existingProductId !== null) {
                            $this->updateAction->handle((string) $existingProductId, $payload);
                        } else {
                            $this->createAction->handle($payload);
                        }
                    });
                } catch (ProductImportOptionResolutionException $e) {
                    $resolver->forget();
                    $failed++;
                    $errors = array_merge($errors, $e->errors);

                    continue;
                } catch (Throwable $e) {
                    if ($resolveOnWrite) {
                        $resolver->forget();
                    }
                    report($e);
                    $failed++;
                    $errors[] = ['row' => $first['line'], 'messages' => ['The row could not be imported.']];

                    continue;
                }
            }

            $existingProductId !== null ? $updated++ : $created++;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $record = CatalogImport::query()->create([
            CatalogImportSchema::USER_ID => Auth::id(),
            CatalogImportSchema::MEDIA_ID => $media->id,
            CatalogImportSchema::FILENAME => $media->name.($media->extension ? '.'.$media->extension : ''),
            CatalogImportSchema::ENTITY => CatalogImportEntityEnum::PRODUCT->value,
            CatalogImportSchema::STATUS => ($failed > 0 && $created + $updated + $skipped === 0)
                ? CatalogImportStatusEnum::FAILED->value
                : CatalogImportStatusEnum::COMPLETED->value,
            CatalogImportSchema::STRATEGY => $strategy->value,
            CatalogImportSchema::DRY_RUN => $dryRun,
            CatalogImportSchema::TOTAL_ROWS => count($rows),
            CatalogImportSchema::CREATED_COUNT => $created,
            CatalogImportSchema::UPDATED_COUNT => $updated,
            CatalogImportSchema::SKIPPED_COUNT => $skipped,
            CatalogImportSchema::FAILED_COUNT => $failed,
            CatalogImportSchema::DURATION_MS => $durationMs,
            CatalogImportSchema::ERRORS => array_slice($errors, 0, self::ERROR_CAP),
        ]);

        return [
            'id' => $record->getKey(),
            'status' => $record->{CatalogImportSchema::STATUS}->value,
            'strategy' => $strategy->value,
            'dry_run' => $dryRun,
            'total_rows' => count($rows),
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
            'duration_ms' => $durationMs,
            'errors' => array_slice($errors, 0, self::ERROR_CAP),
        ];
    }

    /**
     * @throws ImportFailedException
     */
    protected function resolveCsv(int $mediaId): MediaDto
    {
        $media = $this->mediaGateway->findById($mediaId);

        if (! $media || mb_strtolower(trim((string) $media->extension)) !== 'csv') {
            throw new ImportFailedException(meta: ['reason' => 'invalid_media']);
        }

        return $media;
    }

    /**
     * @return array<int, string>
     */
    protected function languageCodes(): array
    {
        return $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE)
            ->values()
            ->all();
    }

    protected function defaultLanguageCode(): string
    {
        $default = $this->coreGateway->getDefaultLanguage();

        if ($default?->code) {
            return $default->code;
        }

        return $this->languageCodes()[0] ?? 'en';
    }

    /**
     * Row rules mirroring ProductRequest: sku/price required, the same
     * price regex, status in the product enum, and per-language title
     * required for the store's default language only.
     *
     * @param  array<int, string>  $languageCodes
     * @return array<string, array<int, string|EnumRule>>
     */
    private function rowRules(array $languageCodes, string $defaultCode): array
    {
        $rules = ProductImportFields::baseRowRules();

        foreach ($languageCodes as $code) {
            $required = $code === $defaultCode ? 'required' : 'nullable';
            $rules[ProductImportFields::TITLE_PREFIX.$code] = [$required, 'string', 'max:255'];
            $rules[ProductImportFields::SLUG_PREFIX.$code] = ['nullable', 'string', 'max:255'];
            $rules[ProductImportFields::DESCRIPTION_PREFIX.$code] = ['nullable', 'string', 'max:255'];
            $rules[ProductImportFields::CONTENT_PREFIX.$code] = ['nullable', 'string'];
        }

        return $rules;
    }

    /**
     * Field keys contain dots (`variant.sku`), which the validator would
     * read as nested paths — so rows are validated under dot-free keys
     * with the real names restored for the error messages.
     *
     * @param  array<string, string>  $values
     * @param  array<string, array<int, string|EnumRule>>  $rules
     */
    private function validateRow(array $values, array $rules): \Illuminate\Validation\Validator
    {
        $safe = fn (string $field) => str_replace('.', '__', $field);

        $safeRules = [];
        $attributeNames = [];
        foreach ($rules as $field => $fieldRules) {
            $safeRules[$safe($field)] = $fieldRules;
            $attributeNames[$safe($field)] = $field;
        }

        $safeValues = [];
        foreach ($values as $field => $value) {
            $safeValues[$safe($field)] = $value;
        }

        $validator = Validator::make($safeValues, $safeRules);
        $validator->setAttributeNames($attributeNames);

        return $validator;
    }

    /**
     * Attach the resolved option value ids of each row to its variant.
     * Returns row-numbered errors (empty means every pair resolved).
     *
     * @param  array<int, array{line: int, values: array<string, string>, messages: array<int, string>}>  $groupRows
     * @param  array<int, array<int, array{0: string, 1: string}>>  $pairsByRow
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<int, array{row: int, messages: array<int, string>}>
     */
    private function attachOptionValueIds(
        array $groupRows,
        array $pairsByRow,
        array &$variants,
        ProductImportOptionResolver $resolver,
    ): array {
        $messagesByLine = [];
        foreach ($groupRows as $i => $row) {
            $ids = [];
            foreach ($pairsByRow[$i] as [$name, $value]) {
                [$id, $message] = $resolver->resolve($name, $value);
                if ($message !== null) {
                    $messagesByLine[$row['line']][] = $message;

                    continue;
                }

                $ids[] = $id;
            }

            if ($ids !== []) {
                $variants[$i][VariantSchema::REQ_OPTION_VALUE_IDS] = array_values(array_unique($ids));
            }
        }

        $errors = [];
        foreach ($messagesByLine as $line => $messages) {
            $errors[] = ['row' => $line, 'messages' => $messages];
        }

        return $errors;
    }

    /**
     * Assemble the Create/UpdateProductAction payload of one product
     * group; the type follows from whether any row carried options.
     *
     * @param  array<string, string>  $firstValues
     * @param  array<int, int>  $categoryIds
     * @param  array<int, array<string, mixed>>  $translations
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<string, mixed>
     */
    private function buildPayload(
        bool $isVariable,
        array $firstValues,
        string $published,
        array $categoryIds,
        array $translations,
        array $variants,
    ): array {
        return [
            ProductSchema::TYPE => $isVariable
                ? ProductTypeEnum::VARIABLE->value
                : ProductTypeEnum::SIMPLE->value,
            ProductSchema::STATUS => ($firstValues[ProductImportFields::FIELD_STATUS] ?? '') ?: ProductStatusEnum::IN_STOCK->value,
            ProductSchema::IS_PUBLISHED => ProductImportFields::truthy($published),
            ProductSchema::RES_CATEGORIES => $categoryIds,
            ProductSchema::RES_TRANSLATIONS => $translations,
            ProductSchema::RES_VARIANTS => $variants,
        ];
    }

    /**
     * @return array{0: array<int, int>, 1: array<int, string>}
     */
    private function resolveCategories(string $cell, array $categoryIdBySlug): array
    {
        if (trim($cell) === '') {
            return [[], []];
        }

        $slugs = array_values(array_filter(array_map('trim', explode('|', $cell))));
        $messages = [];

        if (count($slugs) > 5) {
            $messages[] = 'A product can be linked to at most 5 categories.';
        }

        $ids = [];
        foreach ($slugs as $slug) {
            if (isset($categoryIdBySlug[$slug])) {
                $ids[] = (int) $categoryIdBySlug[$slug];
            } else {
                $messages[] = "Unknown category slug: {$slug}.";
            }
        }

        return [$ids, $messages];
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<int, string>  $languageCodes
     * @param  array<string, int>  $languageIdByCode
     * @param  array<string, array<int, string>>  $usedSlugsByCode
     * @return array<int, array<string, mixed>>
     */
    private function buildTranslations(
        array $values,
        string $sku,
        array $languageCodes,
        array $languageIdByCode,
        ?int $ignoreProductId,
        array &$usedSlugsByCode,
    ): array {
        $translations = [];

        foreach ($languageCodes as $code) {
            $title = (string) ($values[ProductImportFields::TITLE_PREFIX.$code] ?? '');
            if ($title === '') {
                continue;
            }

            $givenSlug = (string) ($values[ProductImportFields::SLUG_PREFIX.$code] ?? '');
            $base = $givenSlug !== ''
                ? ProductImportSlug::slugify($givenSlug)
                : ProductImportSlug::forTitle($title, $sku);
            if ($base === '') {
                $base = ProductImportSlug::forTitle($title, $sku);
            }

            if (! isset($usedSlugsByCode[$code])) {
                $usedSlugsByCode[$code] = [];
            }

            $slug = $this->uniqueSlug(
                $base,
                $languageIdByCode[$code],
                $ignoreProductId,
                $usedSlugsByCode[$code],
            );

            $description = (string) ($values[ProductImportFields::DESCRIPTION_PREFIX.$code] ?? '');

            $translations[] = [
                'language_id' => $languageIdByCode[$code],
                'title' => $title,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'content' => (string) ($values[ProductImportFields::CONTENT_PREFIX.$code] ?? ''),
            ];
        }

        return $translations;
    }

    /**
     * Per-language slugs unique across the store (ignoring the product
     * being updated) and across earlier rows of this file, numbering
     * suffixes on collision exactly like LanguageUniquePair does.
     *
     * @param  array<int, string>  $used
     */
    private function uniqueSlug(string $base, int $languageId, ?int $ignoreProductId, array &$used): string
    {
        $candidate = $base;
        $suffix = 0;

        while ($this->slugTaken($candidate, $languageId, $ignoreProductId)
            || in_array($candidate, $used, true)) {
            $candidate = $base.'-'.(++$suffix);
        }

        $used[] = $candidate;

        return $candidate;
    }

    private function slugTaken(string $slug, int $languageId, ?int $ignoreProductId): bool
    {
        return ProductTranslation::query()
            ->where(ProductTranslationSchema::LANGUAGE_ID, $languageId)
            ->where(ProductTranslationSchema::SLUG, $slug)
            ->when($ignoreProductId !== null, fn ($query) => $query
                ->where(ProductTranslationSchema::PRODUCT_ID, '!=', $ignoreProductId))
            ->exists();
    }
}
