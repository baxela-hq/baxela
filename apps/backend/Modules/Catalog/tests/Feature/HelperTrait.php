<?php

namespace Modules\Catalog\Tests\Feature;

use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Core\Models\Language;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/catalog'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * An in-stock, published product with an English translation — the
     * minimum the public catalog endpoints and comment threads need.
     */
    public function commentableProduct(string $slug = 'commentable-product'): Product
    {
        $product = Product::factory()->create([
            ProductSchema::STATUS => ProductStatusEnum::IN_STOCK,
            ProductSchema::IS_PUBLISHED => true,
        ]);

        $product->translations()->create([
            PTSchema::LANGUAGE_ID => defaultLanguage()->id,
            PTSchema::TITLE => 'Commentable Product',
            PTSchema::SLUG => $slug,
            PTSchema::CONTENT => 'Content',
        ]);

        return $product;
    }
}

/**
 * The default English language row — translation payloads resolve their
 * `language` codes against it. Shared by this module's test files.
 */
function defaultLanguage(): Language
{
    return Language::query()->firstOrCreate(
        ['code' => 'en'],
        ['locale' => 'en', 'name' => 'English', 'code3' => 'eng', 'is_default' => true, 'is_active' => true],
    );
}
