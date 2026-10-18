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

function reviewOn(Product $product, int $rating, ProductCommentStatusEnum $status = ProductCommentStatusEnum::APPROVED): ProductComment
{
    return ProductComment::factory()->create([
        ProductCommentSchema::PRODUCT_ID => $product->id,
        ProductCommentSchema::PARENT_ID => null,
        ProductCommentSchema::RATING => $rating,
        ProductCommentSchema::STATUS => $status,
    ]);
}

/** Seed the truthful aggregate for already-approved reviews (the backfill path). */
function syncAggregate(Product $product): void
{
    RecalculatesProductRating::recalculate($product->id);
}

function moderate(ProductComment $comment, array $overrides): void
{
    test()->patchJson(test()->baseUrl('/admin/product-comments/'.$comment->{ProductCommentSchema::ID}), array_merge([
        ProductCommentSchema::PRODUCT_ID => $comment->{ProductCommentSchema::PRODUCT_ID},
        ProductCommentSchema::PARENT_ID => null,
        ProductCommentSchema::BODY => $comment->{ProductCommentSchema::BODY},
        ProductCommentSchema::RATING => $comment->{ProductCommentSchema::RATING},
        ProductCommentSchema::STATUS => ProductCommentStatusEnum::APPROVED->value,
    ], $overrides))->assertOk();
}

it('recalculates the product average when a review is approved', function () {
    $this->actingAs($this->superAdminUser());
    $product = $this->commentableProduct();

    $pending = reviewOn($product, 4, ProductCommentStatusEnum::PENDING);
    reviewOn($product, 5);
    syncAggregate($product);

    expect($product->refresh()->{ProductSchema::RATING_AVG})->toBe('5.00')
        ->and($product->{ProductSchema::RATING_COUNT})->toBe(1);

    moderate($pending, []);

    expect($product->refresh()->{ProductSchema::RATING_AVG})->toBe('4.50')
        ->and($product->{ProductSchema::RATING_COUNT})->toBe(2);
});

it('drops a review from the average when it is rejected', function () {
    $this->actingAs($this->superAdminUser());
    $product = $this->commentableProduct();

    reviewOn($product, 4);
    $reject = reviewOn($product, 2);
    syncAggregate($product);

    moderate($reject, [ProductCommentSchema::STATUS => ProductCommentStatusEnum::REJECTED->value]);

    expect($product->refresh()->{ProductSchema::RATING_AVG})->toBe('4.00')
        ->and($product->{ProductSchema::RATING_COUNT})->toBe(1);
});

it('recounts the average when a review is deleted', function () {
    $this->actingAs($this->superAdminUser());
    $product = $this->commentableProduct();

    reviewOn($product, 4);
    $delete = reviewOn($product, 5);
    syncAggregate($product);

    $this->deleteJson($this->baseUrl('/admin/product-comments/'.$delete->{ProductCommentSchema::ID}))
        ->assertNoContent();

    expect($product->refresh()->{ProductSchema::RATING_AVG})->toBe('4.00')
        ->and($product->{ProductSchema::RATING_COUNT})->toBe(1);
});

it('updates the average when a review rating is edited', function () {
    $this->actingAs($this->superAdminUser());
    $product = $this->commentableProduct();

    $review = reviewOn($product, 3);
    syncAggregate($product);

    moderate($review, [ProductCommentSchema::RATING => 5]);

    expect($product->refresh()->{ProductSchema::RATING_AVG})->toBe('5.00')
        ->and($product->{ProductSchema::RATING_COUNT})->toBe(1);
});

it('recalculates both products when a review moves to another product', function () {
    $this->actingAs($this->superAdminUser());
    $source = $this->commentableProduct();
    $target = $this->commentableProduct('target-product');

    $review = reviewOn($source, 3);
    syncAggregate($source);

    moderate($review, [ProductCommentSchema::PRODUCT_ID => $target->id]);

    expect($source->refresh()->{ProductSchema::RATING_COUNT})->toBe(0)
        ->and($source->{ProductSchema::RATING_AVG})->toBeNull()
        ->and($target->refresh()->{ProductSchema::RATING_COUNT})->toBe(1)
        ->and($target->{ProductSchema::RATING_AVG})->toBe('3.00');
});

it('resets the aggregate when the last review is rejected', function () {
    $this->actingAs($this->superAdminUser());
    $product = $this->commentableProduct();

    $review = reviewOn($product, 5);
    syncAggregate($product);

    moderate($review, [ProductCommentSchema::STATUS => ProductCommentStatusEnum::REJECTED->value]);

    expect($product->refresh()->{ProductSchema::RATING_COUNT})->toBe(0)
        ->and($product->{ProductSchema::RATING_AVG})->toBeNull();
});
