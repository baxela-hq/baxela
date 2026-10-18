<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Catalog\Tests\Feature\HelperTrait;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('lets an authenticated customer review, stored as pending with its rating', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $product = $this->commentableProduct();

    $response = $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'Great product!',
        'parent_id' => null,
        'rating' => 4,
    ])->assertCreated();

    $comment = ProductComment::query()->find($response->json('data.id'));

    expect($comment)->not->toBeNull()
        ->and($comment->{ProductCommentSchema::BODY})->toBe('Great product!')
        ->and((int) $comment->{ProductCommentSchema::USER_ID})->toBe($user->id)
        ->and($comment->{ProductCommentSchema::RATING})->toBe(4)
        // moderation gate: nothing is public until an admin approves
        ->and($comment->{ProductCommentSchema::STATUS})->toBe(ProductCommentStatusEnum::PENDING);
});

it('rejects guest comments with 401', function () {
    $product = $this->commentableProduct();

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'Great product!',
        'parent_id' => null,
        'rating' => 4,
    ])->assertStatus(401);
});

it('rejects an invalid comment payload', function () {
    $this->actingAs(User::factory()->create());
    $product = $this->commentableProduct();

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => null,
        'parent_id' => null,
        'rating' => 4,
    ])->assertStatus(422)->assertJsonPath('code', 'http.422');

    expect(ProductComment::count())->toBe(0);
});

it('rejects a parent comment from another product', function () {
    $this->actingAs(User::factory()->create());

    $product = $this->commentableProduct();
    $otherProduct = $this->commentableProduct('other-product');

    $parent = ProductComment::factory()->create([
        'product_id' => $otherProduct->id,
        'status' => ProductCommentStatusEnum::APPROVED,
    ]);

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'Reply attempt',
        'parent_id' => $parent->id,
    ])->assertStatus(400)->assertJsonPath('code', 'catalog.product_comment.invalid_parent');
});

it('requires a rating on top-level reviews', function () {
    $this->actingAs(User::factory()->create());
    $product = $this->commentableProduct();

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'No stars given',
        'parent_id' => null,
    ])->assertStatus(422)->assertJsonPath('code', 'http.422');

    expect(ProductComment::count())->toBe(0);
});

it('rejects ratings outside the 1-5 range', function (int $rating) {
    $this->actingAs(User::factory()->create());
    $product = $this->commentableProduct();

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'Out of range',
        'parent_id' => null,
        'rating' => $rating,
    ])->assertStatus(422)->assertJsonPath('code', 'http.422');

    expect(ProductComment::count())->toBe(0);
})->with([0, 6]);

it('forbids ratings on replies', function () {
    $this->actingAs(User::factory()->create());
    $product = $this->commentableProduct();

    $parent = ProductComment::factory()->create([
        'product_id' => $product->id,
        'status' => ProductCommentStatusEnum::APPROVED,
    ]);

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'Reply with stars',
        'parent_id' => $parent->id,
        'rating' => 5,
    ])->assertStatus(422)->assertJsonPath('code', 'http.422');

    expect(ProductComment::query()->whereNotNull(ProductCommentSchema::PARENT_ID)->count())->toBe(0);
});
