<?php

namespace Modules\Catalog\Support\ProductImport;

use Illuminate\Validation\Rules\Enum;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;

/**
 * Field registry for the CSV product import. Template columns follow the
 * Shopify convention: variant fields carry a `variant.` prefix mirroring
 * the variants[0] payload, product-level fields are bare, translations
 * use `field.{language_code}`. Since columns are user-mappable, these
 * keys are the template convention, not a hard requirement.
 */
final class ProductImportFields
{
    public const string FIELD_SKU = 'variant.sku';

    public const string FIELD_PRICE = 'variant.price';

    public const string FIELD_COMPARE_PRICE = 'variant.compare_price';

    public const string FIELD_COST_PRICE = 'variant.cost_price';

    public const string FIELD_QUANTITY = 'variant.quantity';

    public const string FIELD_BARCODE = 'variant.barcode';

    public const string FIELD_STATUS = 'status';

    public const string FIELD_IS_PUBLISHED = 'is_published';

    public const string FIELD_CATEGORIES = 'categories';

    public const string TITLE_PREFIX = 'title.';

    public const string SLUG_PREFIX = 'slug.';

    public const string DESCRIPTION_PREFIX = 'description.';

    public const string CONTENT_PREFIX = 'content.';

    /**
     * @return array<int, string>
     */
    public static function variantFields(): array
    {
        return [
            self::FIELD_SKU,
            self::FIELD_PRICE,
            self::FIELD_COMPARE_PRICE,
            self::FIELD_COST_PRICE,
            self::FIELD_QUANTITY,
            self::FIELD_BARCODE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function baseFields(): array
    {
        return [
            self::FIELD_STATUS,
            self::FIELD_IS_PUBLISHED,
            self::FIELD_CATEGORIES,
        ];
    }

    /**
     * @param  array<int, string>  $languageCodes
     * @return array<int, string>
     */
    public static function translationFields(array $languageCodes): array
    {
        $fields = [];
        foreach ($languageCodes as $code) {
            $fields = array_merge($fields, [
                self::TITLE_PREFIX.$code,
                self::SLUG_PREFIX.$code,
                self::DESCRIPTION_PREFIX.$code,
                self::CONTENT_PREFIX.$code,
            ]);
        }

        return $fields;
    }

    /**
     * All mappable field keys for the given active languages.
     *
     * @param  array<int, string>  $languageCodes
     * @return array<int, string>
     */
    public static function all(array $languageCodes): array
    {
        return array_merge(
            self::variantFields(),
            self::baseFields(),
            self::translationFields($languageCodes),
        );
    }

    /**
     * Fields an import mapping must cover: sku, price and the default
     * language's title.
     *
     * @return array<int, string>
     */
    public static function required(string $defaultLanguageCode): array
    {
        return [
            self::FIELD_SKU,
            self::FIELD_PRICE,
            self::TITLE_PREFIX.$defaultLanguageCode,
        ];
    }

    /**
     * Field keys grouped for the mapping UI: variant fields, base product
     * fields, then one group per active language.
     *
     * @param  array<int, string>  $languageCodes
     * @return array<string, array<int, string>>
     */
    public static function groups(array $languageCodes): array
    {
        $groups = [
            'variant' => self::variantFields(),
            'base' => self::baseFields(),
        ];

        foreach ($languageCodes as $code) {
            $groups[$code] = [
                self::TITLE_PREFIX.$code,
                self::SLUG_PREFIX.$code,
                self::DESCRIPTION_PREFIX.$code,
                self::CONTENT_PREFIX.$code,
            ];
        }

        return $groups;
    }

    /**
     * Normalize a header or field key for fuzzy matching: lowercase,
     * BOM stripped, every non letter/number run collapsed to one space.
     * `Variant SKU`, `variant.sku` and `variant_sku` all become
     * `variant sku`; `title (EN)` becomes `title en`.
     */
    public static function normalize(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim($value);
    }

    /**
     * Match file headers to field keys via their normalized forms.
     * Headers that match no field map to null (ignored columns).
     *
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $languageCodes
     * @return array<int, string|null>
     */
    public static function suggestMapping(array $headers, array $languageCodes): array
    {
        $byNormalized = [];
        foreach (self::all($languageCodes) as $field) {
            $byNormalized[self::normalize($field)] = $field;
        }

        return array_map(
            fn (string $header) => $byNormalized[self::normalize($header)] ?? null,
            $headers,
        );
    }

    /**
     * Row-level rules mirroring ProductRequest for the raw CSV cells of
     * one data row. Language-scoped title/slug/description/content rules
     * are appended by the importer for every active language.
     *
     * @return array<string, array<int, string|Enum>>
     */
    public static function baseRowRules(): array
    {
        $priceRegex = '/^\d{1,12}(\.\d{1,2})?$/';

        return [
            self::FIELD_SKU => ['required', 'string', 'max:255'],
            self::FIELD_PRICE => ['required', 'regex:'.$priceRegex],
            self::FIELD_COMPARE_PRICE => ['nullable', 'regex:'.$priceRegex],
            self::FIELD_COST_PRICE => ['nullable', 'regex:'.$priceRegex],
            self::FIELD_QUANTITY => ['nullable', 'integer', 'min:0'],
            self::FIELD_BARCODE => ['nullable', 'string', 'max:255'],
            self::FIELD_STATUS => ['nullable', new Enum(ProductStatusEnum::class)],
        ];
    }

    /**
     * The loose is_published cell vocabulary: 1/0, true/false, yes/no.
     *
     * @return array<int, string>
     */
    public static function publishedVocabulary(): array
    {
        return ['1', '0', 'true', 'false', 'yes', 'no'];
    }

    /**
     * Interpret an is_published cell: anything but 1/true/yes means the
     * product stays unpublished.
     */
    public static function truthy(?string $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    /**
     * Whether a `prefix.*` field key addresses the given language code.
     */
    public static function isForLanguage(string $field, string $prefix): bool
    {
        if (! str_starts_with($field, $prefix)) {
            return false;
        }

        $code = substr($field, mb_strlen($prefix));

        return mb_strlen($code) === 2;
    }
}
