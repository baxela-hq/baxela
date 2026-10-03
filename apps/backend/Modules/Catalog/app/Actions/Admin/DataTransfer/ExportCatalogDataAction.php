<?php

namespace Modules\Catalog\Actions\Admin\DataTransfer;

use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTranslationSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupTranslationSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema;
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
use Modules\Catalog\Schemas\Product\ProductSeoTranslationSchema as PSTSchema;
use Modules\Catalog\Schemas\Product\ProductShippingSchema as PSSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema as VSchema;
use Modules\Catalog\Support\DataTransfer\CatalogTransferFormat;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

/**
 * Build the whole-module export file: every catalog entity as a request-
 * shaped payload, sections ordered so an importer can replay them with
 * reference remapping. Translations carry language codes, ids stay the
 * exporting store's `source_id`s — portability comes from the importer
 * remapping, not from the export itself.
 */
class ExportCatalogDataAction
{
    public function __construct(protected CoreGatewayInterface $coreGateway) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $codeByLanguageId = $this->codeByLanguageId();

        return [
            CatalogTransferFormat::KEY_FORMAT => CatalogTransferFormat::FORMAT,
            CatalogTransferFormat::KEY_MODULE => CatalogTransferFormat::MODULE,
            CatalogTransferFormat::KEY_VERSION => CatalogTransferFormat::VERSION,
            CatalogTransferFormat::KEY_EXPORTED_AT => now()->toIso8601String(),
            CatalogTransferFormat::KEY_SECTIONS => [
                $this->section(CatalogTransferFormat::SECTION_ATTRIBUTE_GROUPS, $this->attributeGroupRows($codeByLanguageId)),
                $this->section(CatalogTransferFormat::SECTION_ATTRIBUTES, $this->attributeRows($codeByLanguageId)),
                $this->section(CatalogTransferFormat::SECTION_ATTRIBUTE_VALUES, $this->attributeValueRows($codeByLanguageId)),
                $this->section(CatalogTransferFormat::SECTION_OPTIONS, $this->optionRows($codeByLanguageId)),
                $this->section(CatalogTransferFormat::SECTION_OPTION_VALUES, $this->optionValueRows($codeByLanguageId)),
                $this->section(CatalogTransferFormat::SECTION_CATEGORIES, $this->categoryRows($codeByLanguageId)),
                $this->section(CatalogTransferFormat::SECTION_PRODUCTS, $this->productRows($codeByLanguageId)),
                $this->section(CatalogTransferFormat::SECTION_FEATURED_ITEMS, $this->featuredItemRows()),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function section(string $entity, array $rows): array
    {
        return [
            CatalogTransferFormat::KEY_ENTITY => $entity,
            CatalogTransferFormat::KEY_ROWS => $rows,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attributeGroupRows(array $codeByLanguageId): array
    {
        $rows = [];
        AttributeGroup::query()
            ->with(AttributeGroupSchema::RES_TRANSLATIONS)
            ->chunkById(200, function ($groups) use (&$rows, $codeByLanguageId): void {
                foreach ($groups as $group) {
                    $rows[] = [
                        CatalogTransferFormat::KEY_SOURCE_ID => (int) $group->getKey(),
                        CatalogTransferFormat::KEY_PAYLOAD => [
                            AttributeGroupSchema::POSITION => $group->{AttributeGroupSchema::POSITION},
                            AttributeGroupSchema::RES_TRANSLATIONS => $this->translations(
                                $group->{AttributeGroupSchema::RES_TRANSLATIONS},
                                $codeByLanguageId,
                                AttributeGroupTranslationSchema::LANGUAGE_ID,
                                [AttributeGroupTranslationSchema::TITLE],
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attributeRows(array $codeByLanguageId): array
    {
        $rows = [];
        Attribute::query()
            ->with(AttributeSchema::RES_TRANSLATIONS)
            ->chunkById(200, function ($attributes) use (&$rows, $codeByLanguageId): void {
                foreach ($attributes as $attribute) {
                    $rows[] = [
                        CatalogTransferFormat::KEY_SOURCE_ID => (int) $attribute->getKey(),
                        CatalogTransferFormat::KEY_PAYLOAD => [
                            // Source-store group id; remapped on import.
                            AttributeSchema::GROUP_ID => (int) $attribute->{AttributeSchema::GROUP_ID},
                            AttributeSchema::CODE => $attribute->{AttributeSchema::CODE},
                            AttributeSchema::DATA_TYPE => $attribute->{AttributeSchema::DATA_TYPE}->value,
                            AttributeSchema::IS_FILTERABLE => (bool) $attribute->{AttributeSchema::IS_FILTERABLE},
                            AttributeSchema::POSITION => $attribute->{AttributeSchema::POSITION},
                            AttributeSchema::RES_TRANSLATIONS => $this->translations(
                                $attribute->{AttributeSchema::RES_TRANSLATIONS},
                                $codeByLanguageId,
                                AttributeTranslationSchema::LANGUAGE_ID,
                                [AttributeTranslationSchema::TITLE],
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attributeValueRows(array $codeByLanguageId): array
    {
        $rows = [];
        AttributeValue::query()
            ->with(AttributeValueSchema::RES_TRANSLATIONS)
            ->orderBy(AttributeValueSchema::ATTRIBUTE_ID)
            ->orderBy(AttributeValueSchema::POSITION)
            ->chunkById(200, function ($values) use (&$rows, $codeByLanguageId): void {
                foreach ($values as $value) {
                    $rows[] = [
                        CatalogTransferFormat::KEY_SOURCE_ID => (int) $value->getKey(),
                        CatalogTransferFormat::KEY_OWNER => (int) $value->{AttributeValueSchema::ATTRIBUTE_ID},
                        CatalogTransferFormat::KEY_PAYLOAD => [
                            AttributeValueSchema::POSITION => $value->{AttributeValueSchema::POSITION},
                            AttributeValueSchema::RES_TRANSLATIONS => $this->translations(
                                $value->{AttributeValueSchema::RES_TRANSLATIONS},
                                $codeByLanguageId,
                                AttributeValueTranslationSchema::LANGUAGE_ID,
                                [AttributeValueTranslationSchema::TITLE],
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function optionRows(array $codeByLanguageId): array
    {
        $rows = [];
        Option::query()
            ->with(OptionSchema::RES_TRANSLATIONS)
            ->chunkById(200, function ($options) use (&$rows, $codeByLanguageId): void {
                foreach ($options as $option) {
                    $rows[] = [
                        CatalogTransferFormat::KEY_SOURCE_ID => (int) $option->getKey(),
                        CatalogTransferFormat::KEY_PAYLOAD => [
                            OptionSchema::POSITION => $option->{OptionSchema::POSITION},
                            OptionSchema::RES_TRANSLATIONS => $this->translations(
                                $option->{OptionSchema::RES_TRANSLATIONS},
                                $codeByLanguageId,
                                OptionTranslationSchema::LANGUAGE_ID,
                                [OptionTranslationSchema::TITLE, OptionTranslationSchema::SLUG],
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function optionValueRows(array $codeByLanguageId): array
    {
        $rows = [];
        OptionValue::query()
            ->with(OptionValueSchema::RES_TRANSLATIONS)
            ->orderBy(OptionValueSchema::OPTION_ID)
            ->orderBy(OptionValueSchema::POSITION)
            ->chunkById(200, function ($values) use (&$rows, $codeByLanguageId): void {
                foreach ($values as $value) {
                    $rows[] = [
                        CatalogTransferFormat::KEY_SOURCE_ID => (int) $value->getKey(),
                        CatalogTransferFormat::KEY_OWNER => (int) $value->{OptionValueSchema::OPTION_ID},
                        CatalogTransferFormat::KEY_PAYLOAD => [
                            OptionValueSchema::POSITION => $value->{OptionValueSchema::POSITION},
                            OptionValueSchema::RES_TRANSLATIONS => $this->translations(
                                $value->{OptionValueSchema::RES_TRANSLATIONS},
                                $codeByLanguageId,
                                OptionValueTranslationSchema::LANGUAGE_ID,
                                [OptionValueTranslationSchema::TITLE, OptionValueTranslationSchema::SLUG],
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * Categories parents-first, so a sequential import can resolve every
     * parent_id before its child row arrives.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categoryRows(array $codeByLanguageId): array
    {
        $categories = Category::query()
            ->with([CategorySchema::RES_TRANSLATIONS, CategorySchema::RES_ATTRIBUTES])
            ->get();

        $childrenByParent = [];
        foreach ($categories as $category) {
            $childrenByParent[$category->{CategorySchema::PARENT_ID} ?? 0][] = $category;
        }

        $rows = [];
        $walk = function (int $parentId) use (&$walk, &$rows, $childrenByParent, $codeByLanguageId): void {
            foreach ($childrenByParent[$parentId] ?? [] as $category) {
                $rows[] = [
                    CatalogTransferFormat::KEY_SOURCE_ID => (int) $category->getKey(),
                    CatalogTransferFormat::KEY_PAYLOAD => [
                        CategorySchema::PARENT_ID => $category->{CategorySchema::PARENT_ID} !== null
                            ? (int) $category->{CategorySchema::PARENT_ID}
                            : null,
                        CategorySchema::POSITION => $category->{CategorySchema::POSITION},
                        CategorySchema::IMAGE_MEDIA_ID => $category->{CategorySchema::IMAGE_MEDIA_ID},
                        CategorySchema::IMAGE_URL => $category->{CategorySchema::IMAGE_URL},
                        // Source-store attribute ids, in pivot position order.
                        CategoryAttributeSchema::REQ_ATTRIBUTE_IDS => $category->{CategorySchema::RES_ATTRIBUTES}
                            ->pluck(AttributeSchema::ID)
                            ->map(fn ($id) => (int) $id)
                            ->all(),
                        CategorySchema::RES_TRANSLATIONS => $this->translations(
                            $category->{CategorySchema::RES_TRANSLATIONS},
                            $codeByLanguageId,
                            CategoryTranslationSchema::LANGUAGE_ID,
                            [CategoryTranslationSchema::TITLE, CategoryTranslationSchema::SLUG, CategoryTranslationSchema::DESCRIPTION],
                        ),
                    ],
                ];

                $walk((int) $category->getKey());
            }
        };
        $walk(0);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productRows(array $codeByLanguageId): array
    {
        $rows = [];
        Product::query()
            ->with([
                ProductSchema::RES_TRANSLATIONS,
                ProductSchema::RES_SEO,
                ProductSchema::RES_SHIPPING,
                ProductSchema::RES_VARIANTS.'.'.ProductSchema::RES_OPTION_VALUES,
                ProductSchema::RES_VARIANTS.'.'.VSchema::RES_IMAGES,
                ProductSchema::RES_CATEGORIES,
                ProductSchema::RES_IMAGES,
                ProductSchema::RES_ATTRIBUTE_VALUES,
            ])
            ->chunkById(100, function ($products) use (&$rows, $codeByLanguageId): void {
                foreach ($products as $product) {
                    $payload = [
                        ProductSchema::TYPE => $product->{ProductSchema::TYPE}->value,
                        ProductSchema::STATUS => $product->{ProductSchema::STATUS}->value,
                        ProductSchema::IS_PUBLISHED => (bool) $product->{ProductSchema::IS_PUBLISHED},
                        ProductSchema::RES_CATEGORIES => $product->{ProductSchema::RES_CATEGORIES}
                            ->pluck(CategorySchema::ID)
                            ->map(fn ($id) => (int) $id)
                            ->all(),
                        ProductSchema::RES_VARIANTS => $this->variantPayloads($product),
                        ProductSchema::RES_TRANSLATIONS => $this->translations(
                            $product->{ProductSchema::RES_TRANSLATIONS},
                            $codeByLanguageId,
                            PTSchema::LANGUAGE_ID,
                            [PTSchema::TITLE, PTSchema::SLUG, PTSchema::CONTENT, PTSchema::DESCRIPTION],
                        ),
                    ];

                    $shipping = $product->{ProductSchema::RES_SHIPPING};
                    if ($shipping !== null) {
                        $payload[ProductSchema::RES_SHIPPING] = [
                            PSSchema::REQUIRES_SHIPPING => (bool) $shipping->{PSSchema::REQUIRES_SHIPPING},
                            PSSchema::WEIGHT => $this->decimal($shipping->{PSSchema::WEIGHT}),
                            PSSchema::WEIGHT_UNIT => $shipping->{PSSchema::WEIGHT_UNIT},
                            PSSchema::PACKAGE_LENGTH => $this->decimal($shipping->{PSSchema::PACKAGE_LENGTH}),
                            PSSchema::PACKAGE_WIDTH => $this->decimal($shipping->{PSSchema::PACKAGE_WIDTH}),
                            PSSchema::PACKAGE_HEIGHT => $this->decimal($shipping->{PSSchema::PACKAGE_HEIGHT}),
                            PSSchema::DIMENSION_UNIT => $shipping->{PSSchema::DIMENSION_UNIT},
                        ];
                    }

                    if ($product->{ProductSchema::RES_IMAGES}->isNotEmpty()) {
                        $payload[ProductSchema::RES_IMAGES] = $product->{ProductSchema::RES_IMAGES}
                            ->map(fn ($image) => [
                                ImageSchema::MEDIA_ID => (int) $image->{ImageSchema::MEDIA_ID},
                                ImageSchema::URL => $image->{ImageSchema::URL},
                                ImageSchema::POSITION => $image->{ImageSchema::POSITION},
                                ImageSchema::COLLECTION => $image->{ImageSchema::COLLECTION}?->value,
                            ])
                            ->all();
                    }

                    if ($product->{ProductSchema::RES_ATTRIBUTE_VALUES}->isNotEmpty()) {
                        $payload[PAVSchema::REQ_ATTRIBUTE_VALUES] = $product->{ProductSchema::RES_ATTRIBUTE_VALUES}
                            ->map(fn ($value) => [
                                PAVSchema::ATTRIBUTE_ID => (int) $value->{PAVSchema::ATTRIBUTE_ID},
                                PAVSchema::ATTRIBUTE_VALUE_ID => $value->{PAVSchema::ATTRIBUTE_VALUE_ID} !== null
                                    ? (int) $value->{PAVSchema::ATTRIBUTE_VALUE_ID}
                                    : null,
                                PAVSchema::TEXT_VALUE => $value->{PAVSchema::TEXT_VALUE},
                                PAVSchema::NUMBER_VALUE => $this->decimal($value->{PAVSchema::NUMBER_VALUE}),
                                PAVSchema::BOOLEAN_VALUE => $value->{PAVSchema::BOOLEAN_VALUE} !== null
                                    ? (bool) $value->{PAVSchema::BOOLEAN_VALUE}
                                    : null,
                            ])
                            ->all();
                    }

                    $seo = $this->seoPayloads($product, $codeByLanguageId);
                    if ($seo !== []) {
                        $payload[ProductSchema::RES_SEO] = $seo;
                    }

                    $rows[] = [
                        CatalogTransferFormat::KEY_SOURCE_ID => (int) $product->getKey(),
                        CatalogTransferFormat::KEY_PAYLOAD => $payload,
                    ];
                }
            });

        return $rows;
    }

    /**
     * The featured selections replay as a single full-sync row shaped
     * like the admin update endpoint's payload; source ids of the
     * featured products/categories, array order = position.
     *
     * @return array<int, array<string, mixed>>
     */
    private function featuredItemRows(): array
    {
        $idsOf = fn (string $type) => FeaturedItem::query()
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, $type)
            ->orderBy(FeaturedItemSchema::POSITION)
            ->pluck(FeaturedItemSchema::FEATUREDABLE_ID)
            ->map(fn ($id) => (int) $id)
            ->all();

        $productIds = $idsOf(FeaturedItemSchema::TYPE_PRODUCT);
        $categoryIds = $idsOf(FeaturedItemSchema::TYPE_CATEGORY);

        if ($productIds === [] && $categoryIds === []) {
            return [];
        }

        return [
            [
                CatalogTransferFormat::KEY_SOURCE_ID => 1,
                CatalogTransferFormat::KEY_PAYLOAD => [
                    FeaturedItemSchema::REQ_PRODUCT_IDS => $productIds,
                    FeaturedItemSchema::REQ_CATEGORY_IDS => $categoryIds,
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function variantPayloads(Product $product): array
    {
        return $product->{ProductSchema::RES_VARIANTS}
            ->map(function ($variant) {
                $payload = [
                    VSchema::SKU => $variant->{VSchema::SKU},
                    VSchema::PRICE => $this->decimal($variant->{VSchema::PRICE}),
                    VSchema::QUANTITY => (int) $variant->{VSchema::QUANTITY},
                    VSchema::IS_DEFAULT => (bool) $variant->{VSchema::IS_DEFAULT},
                ];

                foreach ([
                    VSchema::BARCODE,
                    VSchema::COMPARE_PRICE,
                    VSchema::COST_PRICE,
                ] as $column) {
                    $payload[$column] = $column === VSchema::BARCODE
                        ? $variant->{$column}
                        : $this->decimal($variant->{$column});
                }

                // Source-store option value ids; remapped on import.
                $optionValueIds = $variant->{ProductSchema::RES_OPTION_VALUES}
                    ->pluck(OptionValueSchema::ID)
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
                if ($optionValueIds !== []) {
                    $payload[VSchema::REQ_OPTION_VALUE_IDS] = $optionValueIds;
                }

                $image = $variant->{VSchema::RES_IMAGES}->first();
                if ($image !== null) {
                    $payload[VSchema::RES_IMAGE] = [
                        ImageSchema::MEDIA_ID => (int) $image->{ImageSchema::MEDIA_ID},
                        ImageSchema::URL => $image->{ImageSchema::URL},
                    ];
                }

                return $payload;
            })
            ->all();
    }

    /**
     * Only seo rows with at least one filled field — mirroring what the
     * actions persist after FiltersProductSeoTrait.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seoPayloads(Product $product, array $codeByLanguageId): array
    {
        $fields = [
            PSTSchema::META_TITLE,
            PSTSchema::META_DESCRIPTION,
            PSTSchema::OPEN_GRAPH_TITLE,
            PSTSchema::OPEN_GRAPH_DESCRIPTION,
        ];

        $rows = [];
        foreach ($product->{ProductSchema::RES_SEO} as $seo) {
            $code = $codeByLanguageId[(int) $seo->{PSTSchema::LANGUAGE_ID}] ?? null;
            if ($code === null) {
                continue;
            }

            $row = [PSTSchema::REQ_LANGUAGE => $code];
            $filled = false;
            foreach ($fields as $field) {
                $value = $seo->{$field};
                $row[$field] = $value;
                $filled = $filled || $value !== null;
            }

            if ($filled) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Emit translations keyed by language code, restricted to the given
     * content fields. Rows of inactive languages cannot be keyed and are
     * skipped.
     *
     * @param  iterable<object>  $translations
     * @param  array<int, string>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function translations(iterable $translations, array $codeByLanguageId, string $languageColumn, array $fields): array
    {
        $rows = [];
        foreach ($translations as $translation) {
            $code = $codeByLanguageId[(int) $translation->{$languageColumn}] ?? null;
            if ($code === null) {
                continue;
            }

            $row = ['language' => $code];
            foreach ($fields as $field) {
                $row[$field] = $translation->{$field};
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Decimals as plain strings ("450000", "450000.5") — the exact
     * vocabulary the payload regexes accept, without driver-specific
     * casting quirks.
     */
    private function decimal(int|float|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $formatted = number_format((float) $value, 2, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * @return array<int, string> language id => code, active languages only
     */
    private function codeByLanguageId(): array
    {
        return $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE, 'id')
            ->mapWithKeys(fn ($code, $id) => [(int) $id => $code])
            ->all();
    }
}
