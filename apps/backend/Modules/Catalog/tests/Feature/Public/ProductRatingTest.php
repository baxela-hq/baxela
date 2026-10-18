<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Catalog\Support\RecalculatesProductRating;
use Modules\Catalog\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function ratedProduct(string $slug, array $ratings, array $pendingRatings = []): Product
{
    $product = test()->commentableProduct($slug);

    foreach ($ratings as $rating) {
        ProductComment::factory()->create([
            ProductCommentSchema::PRODUCT_ID => $product->id,
            ProductCommentSchema::PARENT_ID => null,
            ProductCommentSchema::RATING => $rating,
            ProductCommentSchema::STATUS => ProductCommentStatusEnum::APPROVED,
        ]);
    }

    // pending moderation — must never influence the public aggregate
    foreach ($pendingRatings as $rating) {
        ProductComment::factory()->create([
            ProductCommentSchema::PRODUCT_ID => $product->id,
            ProductCommentSchema::PARENT_ID => null,
            ProductCommentSchema::RATING => $rating,
            ProductCommentSchema::STATUS => ProductCommentStatusEnum::PENDING,
        ]);
    }

    RecalculatesProductRating::recalculate($product->id);

    return $product;
}

it('exposes the review aggregate on the public product show', function () {
    $product = ratedProduct('rated-product', [5, 4], [1]);

    $this->getJson($this->baseUrl('/public/products/'.$product->id))
        ->assertOk()
        ->assertJsonPath('data.rating_avg', '4.50')
        ->assertJsonPath('data.rating_count', 2);
});

it('sorts the public product list by rating', function () {
    $low = ratedProduct('low-rated', [2]);
    $high = ratedProduct('high-rated', [5, 4]);
    $unrated = $this->commentableProduct('unrated-product');

    $response = $this->getJson($this->baseUrl('/public/products?sort=-rating'))->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids[0])->toBe($high->id)
        ->and($ids[1])->toBe($low->id)
        // unrated products sink on descending sorts
        ->and($ids->last())->toBe($unrated->id)
        // the aggregate travels with the list payload too
        ->and($response->json('data.0.rating_avg'))->toBe('4.50');
});

it('keeps unrated products listable with null aggregates', function () {
    $product = $this->commentableProduct('plain-product');

    $this->getJson($this->baseUrl('/public/products/'.$product->id))
        ->assertOk()
        ->assertJsonPath('data.rating_avg', null)
        ->assertJsonPath('data.rating_count', 0);
});
