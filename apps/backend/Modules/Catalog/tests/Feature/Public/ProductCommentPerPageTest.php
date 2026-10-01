<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Catalog\Tests\Feature\HelperTrait;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function commentedProduct(int $commentCount = 3): Product
{
    $languageId = defaultLanguage()->id;

    $product = Product::factory()->create([
        'status' => ProductStatusEnum::IN_STOCK,
        'is_published' => true,
    ]);

    $product->translations()->create([
        'language_id' => $languageId,
        'title' => 'Commented Product',
        'slug' => 'commented-product',
        'content' => 'Content',
    ]);

    ProductComment::factory()->count($commentCount)->create([
        ProductCommentSchema::PRODUCT_ID => $product->id,
        ProductCommentSchema::STATUS => ProductCommentStatusEnum::APPROVED,
    ]);

    return $product;
}

it('caps an unbounded per_page on the public comments endpoint', function () {
    $product = commentedProduct();

    $this->getJson($this->baseUrl('/public/products/'.$product->id.'/comments').'?per_page=1000000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('clamps a zero or negative per_page to one', function () {
    $product = commentedProduct();

    $this->getJson($this->baseUrl('/public/products/'.$product->id.'/comments').'?per_page=-5')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
});
