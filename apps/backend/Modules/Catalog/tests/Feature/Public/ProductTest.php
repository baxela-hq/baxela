<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Catalog\Tests\Feature\HelperTrait;
use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function publicProduct(string $slug, bool $published = true): Product
{
    $languageId = defaultLanguage()->id;

    $product = Product::factory()->create([
        'status' => ProductStatusEnum::IN_STOCK,
        'is_published' => $published,
    ]);

    ProductTranslation::query()->create([
        'product_id' => $product->id,
        'language_id' => $languageId,
        'title' => 'Product '.$slug,
        'slug' => $slug,
        'content' => 'Content for '.$slug,
    ]);

    return $product;
}

function commentOn(Product $product, string $body, ProductCommentStatusEnum $status): ProductComment
{
    return ProductComment::factory()->create([
        'product_id' => $product->id,
        'status' => $status,
        'body' => $body,
    ]);
}

it('lists published in-stock products only', function () {
    $visible = publicProduct('visible-product');
    publicProduct('hidden-product', published: false);
    Product::factory()->create([
        'status' => ProductStatusEnum::OUT_OF_STOCK,
        'is_published' => true,
    ]);

    $response = $this->getJson($this->baseUrl('/public/products'))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($visible->id)->toHaveCount(1);
});

it('shows a product by id', function () {
    $product = publicProduct('showable-product');

    $this->getJson($this->baseUrl('/public/products/'.$product->id))
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.slug', 'showable-product');
});

it('returns 404 for an unknown product', function () {
    $this->getJson($this->baseUrl('/public/products/999999'))
        ->assertStatus(404)
        ->assertJsonPath('code', 'http.404');
});

it('lists approved comments with their approved replies for a product', function () {
    $product = publicProduct('commented-product');

    $approved = commentOn($product, 'Approved top-level', ProductCommentStatusEnum::APPROVED);
    commentOn($product, 'Pending top-level', ProductCommentStatusEnum::PENDING);

    ProductComment::factory()->create([
        'product_id' => $product->id,
        'parent_id' => $approved->id,
        'status' => ProductCommentStatusEnum::APPROVED,
        'body' => 'Approved reply',
    ]);
    ProductComment::factory()->create([
        'product_id' => $product->id,
        'parent_id' => $approved->id,
        'status' => ProductCommentStatusEnum::REJECTED,
        'body' => 'Rejected reply',
    ]);

    $response = $this->getJson($this->baseUrl('/public/products/'.$product->id.'/comments'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $comment = $response->json('data.0');
    expect($comment['body'])->toBe('Approved top-level')
        ->and($comment['replies'])->toHaveCount(1)
        ->and($comment['replies'][0]['body'])->toBe('Approved reply');
});
