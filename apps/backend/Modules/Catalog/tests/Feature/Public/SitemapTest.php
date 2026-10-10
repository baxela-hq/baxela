<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Tests\Feature\HelperTrait;
use Modules\Core\Models\Language;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function sitemapProduct(string $slug, array $attributes = []): Product
{
    $product = Product::factory()->create([
        ProductSchema::STATUS => ProductStatusEnum::IN_STOCK,
        ProductSchema::IS_PUBLISHED => true,
        ...$attributes,
    ]);

    $product->translations()->create([
        PTSchema::LANGUAGE_ID => TestCase::defaultLanguage()->id,
        PTSchema::TITLE => 'Product '.$slug,
        PTSchema::SLUG => $slug,
        PTSchema::CONTENT => 'Content',
    ]);

    return $product;
}

it('feeds only publicly visible products to the sitemap', function () {
    TestCase::defaultLanguage();

    $visible = sitemapProduct('visible-product');
    sitemapProduct('unpublished-product', [ProductSchema::IS_PUBLISHED => false]);
    sitemapProduct('sold-out-product', [ProductSchema::STATUS => ProductStatusEnum::OUT_OF_STOCK]);
    sitemapProduct('deleted-product')->delete();

    $this->getJson($this->baseUrl('/public/sitemap'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonStructure([
            'data' => [
                ['id', 'updated_at', 'translations' => [['language', 'slug']]],
            ],
        ]);
});

it('exposes a slug per language for hreflang alternates', function () {
    TestCase::defaultLanguage();
    $fa = Language::query()->firstOrCreate(
        ['code' => 'fa'],
        ['locale' => 'fa', 'name' => 'Persian', 'native_name' => 'فارسی', 'code3' => 'fas', 'is_rtl' => true, 'is_active' => true],
    );

    $product = sitemapProduct('english-slug');
    $product->translations()->create([
        PTSchema::LANGUAGE_ID => $fa->id,
        PTSchema::TITLE => 'کفش ورزشی',
        PTSchema::SLUG => 'persian-slug',
        PTSchema::CONTENT => 'محتوا',
    ]);

    $this->getJson($this->baseUrl('/public/sitemap'))
        ->assertOk()
        ->assertJsonCount(2, 'data.0.translations')
        ->assertJsonPath('data.0.translations.0.language', 'en')
        ->assertJsonPath('data.0.translations.0.slug', 'english-slug')
        ->assertJsonPath('data.0.translations.1.language', 'fa')
        ->assertJsonPath('data.0.translations.1.slug', 'persian-slug');
});
