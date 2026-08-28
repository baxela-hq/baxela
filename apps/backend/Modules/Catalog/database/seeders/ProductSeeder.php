<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Catalog\Actions\Admin\Product\CreateProductAction;
use Modules\Catalog\Exceptions\Product\CreationFailedException;
use Modules\Catalog\Models\CategoryTranslation;
use Modules\Catalog\Models\OptionValueTranslation;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema;
use Modules\Catalog\Schemas\Module;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
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

            $service->handle($payload);

        }
    }
}
