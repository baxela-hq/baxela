<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Models\ProductCommentReaction;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema as RSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Catalog\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function approvedReview(string $productId): ProductComment
{
    return ProductComment::factory()->create([
        ProductCommentSchema::PRODUCT_ID => $productId,
        ProductCommentSchema::PARENT_ID => null,
        ProductCommentSchema::STATUS => ProductCommentStatusEnum::APPROVED,
    ]);
}

it('rejects guest reactions with 401', function () {
    $product = $this->commentableProduct();
    $review = approvedReview($product->id);

    $this->postJson($this->baseUrl('/user/product-comments/'.$review->id.'/reaction'), [
        RSchema::REACTION => ProductCommentReactionEnum::LIKE->value,
    ])->assertStatus(401);
});

it('rejects an unknown reaction value', function () {
    $this->actingAs(User::factory()->create());
    $product = $this->commentableProduct();
    $review = approvedReview($product->id);

    $this->postJson($this->baseUrl('/user/product-comments/'.$review->id.'/reaction'), [
        RSchema::REACTION => 'meh',
    ])->assertStatus(422)->assertJsonPath('code', 'http.422');

    expect(ProductCommentReaction::count())->toBe(0);
});

it('likes, switches and un-likes a comment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $product = $this->commentableProduct();
    $review = approvedReview($product->id);

    $react = fn (string $reaction) => $this->postJson(
        $this->baseUrl('/user/product-comments/'.$review->id.'/reaction'),
        [RSchema::REACTION => $reaction]
    );

    // like
    $react(ProductCommentReactionEnum::LIKE->value)->assertOk()
        ->assertJsonPath('data.reaction', 'like')
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.dislikes_count', 0);

    // switch to dislike
    $react(ProductCommentReactionEnum::DISLIKE->value)->assertOk()
        ->assertJsonPath('data.reaction', 'dislike')
        ->assertJsonPath('data.likes_count', 0)
        ->assertJsonPath('data.dislikes_count', 1);

    // re-sending the same reaction removes it
    $react(ProductCommentReactionEnum::DISLIKE->value)->assertOk()
        ->assertJsonPath('data.reaction', null)
        ->assertJsonPath('data.likes_count', 0)
        ->assertJsonPath('data.dislikes_count', 0);

    expect(ProductCommentReaction::count())->toBe(0);
});

it('keeps one reaction per user per comment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $product = $this->commentableProduct();
    $review = approvedReview($product->id);

    ProductCommentReaction::query()->create([
        RSchema::PRODUCT_COMMENT_ID => $review->id,
        RSchema::USER_ID => $user->id,
        RSchema::REACTION => ProductCommentReactionEnum::LIKE,
    ]);

    $this->postJson($this->baseUrl('/user/product-comments/'.$review->id.'/reaction'), [
        RSchema::REACTION => ProductCommentReactionEnum::DISLIKE->value,
    ])->assertOk();

    expect(ProductCommentReaction::count())->toBe(1)
        ->and(ProductCommentReaction::query()->first()->{RSchema::REACTION})
        ->toBe(ProductCommentReactionEnum::DISLIKE);
});

it('exposes reaction counts on the public thread, replies included', function () {
    $product = $this->commentableProduct();
    $review = approvedReview($product->id);
    $reply = ProductComment::factory()->create([
        ProductCommentSchema::PRODUCT_ID => $product->id,
        ProductCommentSchema::PARENT_ID => $review->id,
        ProductCommentSchema::STATUS => ProductCommentStatusEnum::APPROVED,
    ]);

    foreach ([1, 2] as $userId) {
        ProductCommentReaction::query()->create([
            RSchema::PRODUCT_COMMENT_ID => $review->id,
            RSchema::USER_ID => $userId,
            RSchema::REACTION => ProductCommentReactionEnum::LIKE,
        ]);
    }

    ProductCommentReaction::query()->create([
        RSchema::PRODUCT_COMMENT_ID => $review->id,
        RSchema::USER_ID => 3,
        RSchema::REACTION => ProductCommentReactionEnum::DISLIKE,
    ]);

    ProductCommentReaction::query()->create([
        RSchema::PRODUCT_COMMENT_ID => $reply->id,
        RSchema::USER_ID => 4,
        RSchema::REACTION => ProductCommentReactionEnum::LIKE,
    ]);

    $this->getJson($this->baseUrl('/public/products/'.$product->id.'/comments'))
        ->assertOk()
        ->assertJsonPath('data.0.likes_count', 2)
        ->assertJsonPath('data.0.dislikes_count', 1)
        ->assertJsonPath('data.0.replies.0.likes_count', 1)
        ->assertJsonPath('data.0.replies.0.dislikes_count', 0);
});

it('lists the authenticated user\'s reactions for a product thread', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $product = $this->commentableProduct();

    $review = approvedReview($product->id);
    $otherProductReview = approvedReview($this->commentableProduct('other-product')->id);

    ProductCommentReaction::query()->create([
        RSchema::PRODUCT_COMMENT_ID => $review->id,
        RSchema::USER_ID => $user->id,
        RSchema::REACTION => ProductCommentReactionEnum::LIKE,
    ]);

    // another user's reaction on the same thread — must not leak into mine
    ProductCommentReaction::query()->create([
        RSchema::PRODUCT_COMMENT_ID => $review->id,
        RSchema::USER_ID => 99,
        RSchema::REACTION => ProductCommentReactionEnum::DISLIKE,
    ]);

    // my reaction on another product's thread — must not appear either
    ProductCommentReaction::query()->create([
        RSchema::PRODUCT_COMMENT_ID => $otherProductReview->id,
        RSchema::USER_ID => $user->id,
        RSchema::REACTION => ProductCommentReactionEnum::DISLIKE,
    ]);

    $this->getJson($this->baseUrl('/user/products/'.$product->id.'/comment-reactions'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.comment_id', $review->id)
        ->assertJsonPath('data.0.reaction', 'like');
});
