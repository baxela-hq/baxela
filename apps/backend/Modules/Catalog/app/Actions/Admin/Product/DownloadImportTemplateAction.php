<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Modules\Catalog\Support\ProductImport\ProductImportCsv;
use Modules\Catalog\Support\ProductImport\ProductImportFields;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class DownloadImportTemplateAction
{
    public function __construct(
        protected CoreGatewayInterface $coreGateway,
    ) {}

    /**
     * Build the example CSV (UTF-8 + BOM so Excel re-opens Persian
     * content correctly). Headers are language-aware; the example rows
     * — multi-variant, required-only and optional-fields — keep the
     * file itself importable, so admins can dry-run it to learn the
     * flow.
     */
    public function handle(): string
    {
        $languageCodes = $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE)
            ->values()
            ->all();
        $defaultCode = $this->defaultLanguageCode();

        $columns = array_merge(
            ProductImportFields::variantFields(),
            ProductImportFields::optionFields(),
            [ProductImportFields::FIELD_STATUS, ProductImportFields::FIELD_IS_PUBLISHED],
            ProductImportFields::translationFields($languageCodes),
            [ProductImportFields::FIELD_CATEGORIES],
        );

        $rows = [$columns];
        foreach ($this->exampleRows($languageCodes, $defaultCode) as $example) {
            $rows[] = array_map(
                fn (string $column) => (string) ($example[$column] ?? ''),
                $columns,
            );
        }

        return ProductImportCsv::BOM.ProductImportCsv::render($rows);
    }

    /**
     * Example content per known language code; any other active language
     * falls back to a generic English-ish pair so the template stays
     * valid for every store configuration.
     *
     * @param  array<int, string>  $languageCodes
     * @return array<int, array<string, string>>
     */
    private function exampleRows(array $languageCodes, string $defaultCode): array
    {
        $titles = [
            'fa' => 'تی‌شرت کلاسیک مردانه',
            'en' => "Men's Classic T-Shirt",
        ];
        $descriptions = [
            'fa' => 'تی‌شرت نخی پنبه ۱۰۰٪ با دوخت تقویت‌شده',
            'en' => '100% cotton crew-neck tee',
        ];
        $contents = [
            'fa' => '<p>توضیحات کامل محصول که در صفحه محصول نمایش داده می‌شود.</p>',
            'en' => '<p>Full product description shown on the product page.</p>',
        ];
        $slugs = [
            'fa' => 'تیشرت-کلاسیک-مردانه',
            'en' => 'mens-classic-t-shirt',
        ];
        $optionNames = [
            'fa' => ['رنگ', 'سایز'],
            'en' => ['Color', 'Size'],
        ];
        $optionValues = [
            'fa' => ['قرمز', 'آبی'],
            'en' => ['Red', 'Blue'],
        ];

        $title = fn (string $code) => $titles[$code] ?? "Sample Product ({$code})";
        $slug = fn (string $code) => $slugs[$code] ?? "sample-product-{$code}";
        $optionName = fn (int $index) => $optionNames[$defaultCode][$index]
            ?? $optionNames['en'][$index];
        $optionValue = fn (int $index) => $optionValues[$defaultCode][$index]
            ?? $optionValues['en'][$index];

        // A multi-variant product: three rows share one handle, and
        // only the first row carries the product-level cells.
        $groupedFirst = [
            ProductImportFields::FIELD_SKU => 'BX-TEE-001-RED-M',
            ProductImportFields::FIELD_PRICE => '450000',
            ProductImportFields::FIELD_COMPARE_PRICE => '520000',
            ProductImportFields::FIELD_COST_PRICE => '320000',
            ProductImportFields::FIELD_QUANTITY => '10',
            ProductImportFields::FIELD_BARCODE => '6260101234501',
            ProductImportFields::FIELD_HANDLE => 'mens-classic-tshirt',
            ProductImportFields::optionNameField(1) => $optionName(0),
            ProductImportFields::optionValueField(1) => $optionValue(0),
            ProductImportFields::optionNameField(2) => $optionName(1),
            ProductImportFields::optionValueField(2) => 'M',
            ProductImportFields::FIELD_STATUS => 'in_stock',
            ProductImportFields::FIELD_IS_PUBLISHED => 'yes',
            ProductImportFields::FIELD_CATEGORIES => 'mens-fashion|mens-t-shirts-tanks',
        ];
        foreach ($languageCodes as $code) {
            $groupedFirst[ProductImportFields::TITLE_PREFIX.$code] = $title($code);
            $groupedFirst[ProductImportFields::SLUG_PREFIX.$code] = $slug($code);
            $groupedFirst[ProductImportFields::DESCRIPTION_PREFIX.$code] = $descriptions[$code] ?? "Short description ({$code})";
            $groupedFirst[ProductImportFields::CONTENT_PREFIX.$code] = $contents[$code] ?? "<p>Full description ({$code}).</p>";
        }

        $groupedRow = fn (string $sku, string $colorIndex, string $size) => [
            ProductImportFields::FIELD_SKU => $sku,
            ProductImportFields::FIELD_PRICE => '450000',
            ProductImportFields::FIELD_QUANTITY => '8',
            ProductImportFields::FIELD_HANDLE => 'mens-classic-tshirt',
            ProductImportFields::optionNameField(1) => $optionName(0),
            ProductImportFields::optionValueField(1) => $optionValue($colorIndex),
            ProductImportFields::optionNameField(2) => $optionName(1),
            ProductImportFields::optionValueField(2) => $size,
        ];

        $minimal = [
            ProductImportFields::FIELD_SKU => 'BX-CAP-002',
            ProductImportFields::FIELD_PRICE => '180000',
            ProductImportFields::TITLE_PREFIX.$defaultCode => $titles[$defaultCode] ?? 'Sample Product',
        ];

        $optional = [
            ProductImportFields::FIELD_SKU => 'BX-JEANS-003',
            ProductImportFields::FIELD_PRICE => '1250000.50',
            ProductImportFields::FIELD_COMPARE_PRICE => '1490000',
            ProductImportFields::FIELD_COST_PRICE => '900000.75',
            ProductImportFields::FIELD_QUANTITY => '0',
            ProductImportFields::FIELD_STATUS => 'out_of_stock',
            ProductImportFields::FIELD_IS_PUBLISHED => 'no',
            ProductImportFields::FIELD_CATEGORIES => 'mens-jeans',
            // Slugs left blank on purpose: they are auto-generated.
        ];
        foreach ($languageCodes as $code) {
            $optional[ProductImportFields::TITLE_PREFIX.$code] = $code === 'fa'
                ? 'شلوار جین راسته مردانه'
                : ($titles[$code] ?? "Sample Product ({$code})");
            $optional[ProductImportFields::DESCRIPTION_PREFIX.$code] = $code === 'fa'
                ? 'شلوار جین راسته با پارچه دنیم سنگ‌شور'
                : ($descriptions[$code] ?? "Short description ({$code})");
        }

        return [
            $groupedFirst,
            $groupedRow('BX-TEE-001-RED-L', 0, 'L'),
            $groupedRow('BX-TEE-001-BLU-M', 1, 'M'),
            $minimal,
            $optional,
        ];
    }

    private function defaultLanguageCode(): string
    {
        $default = $this->coreGateway->getDefaultLanguage();

        if ($default?->code) {
            return $default->code;
        }

        $codes = $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE)
            ->values()
            ->all();

        return $codes[0] ?? 'en';
    }
}
