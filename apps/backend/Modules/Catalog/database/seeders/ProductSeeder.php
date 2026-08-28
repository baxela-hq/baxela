<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Catalog\Actions\Admin\Product\CreateProductAction;
use Modules\Catalog\Exceptions\Product\CreationFailedException;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Models\AttributeValueTranslation;
use Modules\Catalog\Models\CategoryTranslation;
use Modules\Catalog\Models\OptionValueTranslation;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema as AVTSchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema;
use Modules\Catalog\Schemas\Module;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema as PAVSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductSeoTranslationSchema as PSTSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;
use Throwable;

class ProductSeeder extends Seeder
{
    private CoreGatewayInterface $coreGateway;

    /** @var array<string, Attribute|null> */
    private array $attributes = [];

    /** @var array<int, array<string, int>> */
    private array $attributeValueIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        //        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();
        $langs = ['en'];
        $moduleKey = Module::NAME_LOWER.'::seeder.products';

        foreach ($langs as $lang) {

            $products = Lang::get($moduleKey, [], $lang);

            Model::reguard();

            ProductTranslation::query()->delete();
            Variant::query()->delete();
            Product::query()->delete();

            $this->generate($products, $lang);
        }
    }

    /**
     * @param  array{title: string, slug: string, children: array}  $products
     *
     * @throws CreationFailedException
     * @throws Throwable
     */
    private function generate(array $products, string $lang, ?int $parentId = null): void
    {
        $languageId = $this->coreGateway->getLanguageIdByCode($lang);
        $currencyId = $this->coreGateway->getDefaultCurrency();

        if (is_null($languageId)) {
            return;
        }

        foreach ($products as $product) {
            $categoryIds = CategoryTranslation::query()
                ->whereIn(CategoryTranslationSchema::SLUG, $product[ProductSchema::RES_CATEGORIES])
                ->pluck(CategoryTranslationSchema::CATEGORY_ID)
                ->toArray();
            $service = app(CreateProductAction::class);
            $product[ProductSchema::RES_VARIANTS] = array_map(function ($variant) {
                $variant[VariantSchema::QUANTITY] = rand(5, 15);

                $variant[VariantSchema::REQ_OPTION_VALUE_IDS] = OptionValueTranslation::query()
                    ->whereIn(OVTSchema::SLUG, array_values($variant['option_values']))
                    ->pluck(OVTSchema::OPTION_VALUE_ID)
                    ->toArray();

                return $variant;
            }, $product[ProductSchema::RES_VARIANTS]);

            $payload = [
                ProductSchema::TYPE => $product[ProductSchema::TYPE],
                ProductSchema::STATUS => ProductStatusEnum::IN_STOCK,
                ProductSchema::IS_PUBLISHED => true,
                ProductSchema::RES_CATEGORIES => $categoryIds,
                ProductSchema::RES_IMAGES => [],
                ProductSchema::RES_VARIANTS => $product[ProductSchema::RES_VARIANTS],
                ProductSchema::RES_TRANSLATIONS => [
                    [
                        PTSchema::LANGUAGE_ID => $languageId,
                        PTSchema::TITLE => $product[PTSchema::TITLE],
                        PTSchema::SLUG => $product[PTSchema::SLUG],
                        PTSchema::DESCRIPTION => $product[PTSchema::DESCRIPTION],
                        PTSchema::CONTENT => $product[PTSchema::CONTENT],
                    ],
                ],
                ProductSchema::RES_SEO => [
                    [
                        PSTSchema::LANGUAGE_ID => $languageId,
                        PSTSchema::META_TITLE => $product[PTSchema::TITLE],
                        PSTSchema::META_DESCRIPTION => $product[PTSchema::DESCRIPTION],
                    ],
                ],
            ];

            if (isset($product[ProductSchema::RES_SHIPPING])) {
                $payload[ProductSchema::RES_SHIPPING] = $product[ProductSchema::RES_SHIPPING];
            }

            if (isset($product[ProductSchema::RES_ATTRIBUTES])) {
                $payload[PAVSchema::REQ_ATTRIBUTE_VALUES] = $this->resolveAttributeValues(
                    $product[ProductSchema::RES_ATTRIBUTES],
                    $languageId,
                );
            }

            $service->handle($payload);

        }
    }

    /**
     * @param  array<int, array{code: string, value: mixed}>  $attributes
     * @return array<int, array<string, mixed>>
     */
    private function resolveAttributeValues(array $attributes, int $languageId): array
    {
        $rows = [];

        foreach ($attributes as $item) {
            $code = $item[AttributeSchema::CODE];

            if (! array_key_exists($code, $this->attributes)) {
                $this->attributes[$code] = Attribute::query()
                    ->where(AttributeSchema::CODE, $code)
                    ->first();
            }

            $attribute = $this->attributes[$code];

            if (is_null($attribute)) {
                continue;
            }

            $row = [
                PAVSchema::ATTRIBUTE_ID => $attribute->{AttributeSchema::ID},
                PAVSchema::ATTRIBUTE_VALUE_ID => null,
                PAVSchema::TEXT_VALUE => null,
                PAVSchema::NUMBER_VALUE => null,
                PAVSchema::BOOLEAN_VALUE => null,
            ];

            switch ($attribute->{AttributeSchema::DATA_TYPE}) {
                case AttributeTypeEnum::BOOLEAN:
                    $row[PAVSchema::BOOLEAN_VALUE] = (bool) $item['value'];

                    break;

                case AttributeTypeEnum::NUMBER:
                    $row[PAVSchema::NUMBER_VALUE] = $item['value'];

                    break;

                case AttributeTypeEnum::SELECT:
                case AttributeTypeEnum::MULTISELECT:
                    $row[PAVSchema::ATTRIBUTE_VALUE_ID] = $this->resolveAttributeValueId(
                        $attribute->{AttributeSchema::ID},
                        $item['value'],
                        $languageId,
                    );

                    break;

                default:
                    $row[PAVSchema::TEXT_VALUE] = $item['value'];

                    break;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function resolveAttributeValueId(int $attributeId, string $title, int $languageId): ?int
    {
        if (! isset($this->attributeValueIds[$attributeId])) {
            $this->attributeValueIds[$attributeId] = AttributeValueTranslation::query()
                ->where(AVTSchema::LANGUAGE_ID, $languageId)
                ->whereIn(AVTSchema::ATTRIBUTE_VALUE_ID, function ($query) use ($attributeId) {
                    $query->select(AttributeValueSchema::ID)
                        ->from(AttributeValueSchema::TABLE)
                        ->where(AttributeValueSchema::ATTRIBUTE_ID, $attributeId);
                })
                ->pluck(AVTSchema::ATTRIBUTE_VALUE_ID, AVTSchema::TITLE)
                ->toArray();
        }

        return $this->attributeValueIds[$attributeId][$title] ?? null;
    }
}
