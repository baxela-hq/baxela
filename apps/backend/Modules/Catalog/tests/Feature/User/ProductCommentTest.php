<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Catalog\Tests\Feature\HelperTrait;
use function Modules\Catalog\Tests\Feature\defaultLanguage;
use Modules\Core\Models\Language;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function commentableProduct(string $slug = 'commentable-product'): Product
{
    $languageId = defaultLanguage()->id;

    $product = Product::factory()->create([
        'status' => ProductStatusEnum::IN_STOCK,
        'is_published' => true,
    ]);

    $product->translations()->create([
        'language_id' => $languageId,
        'title' => 'Commentable Product',
        'slug' => $slug,
        'content' => 'Content',
    ]);

    return $product;
}

it('lets an authenticated customer comment, stored as pending', function () {
    $user = \Modules\Auth\Models\User::factory()->create();
    $this->actingAs($user);
    $product = commentableProduct();

    $response = $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'Great product!',
        'parent_id' => null,
    ])->assertCreated();

    $comment = ProductComment::query()->find($response->json('data.id'));

    expect($comment)->not->toBeNull()
        ->and($comment->{ProductCommentSchema::BODY})->toBe('Great product!')
        ->and((int) $comment->{ProductCommentSchema::USER_ID})->toBe($user->id)
        // moderation gate: nothing is public until an admin approves
        ->and($comment->{ProductCommentSchema::STATUS})->toBe(ProductCommentStatusEnum::PENDING);
});

it('rejects guest comments with 401', function () {
    $product = commentableProduct();

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'Great product!',
        'parent_id' => null,
    ])->assertStatus(401);
});

it('rejects an invalid comment payload', function () {
    $this->actingAs(\Modules\Auth\Models\User::factory()->create());
    $product = commentableProduct();

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => null,
        'parent_id' => null,
    ])->assertStatus(422)->assertJsonPath('code', 'http.422');

    expect(ProductComment::count())->toBe(0);
});

it('rejects a parent comment from another product', function () {
    $this->actingAs(\Modules\Auth\Models\User::factory()->create());

    $product = commentableProduct();
    $otherProduct = commentableProduct('other-product');

    $parent = ProductComment::factory()->create([
        'product_id' => $otherProduct->id,
        'status' => ProductCommentStatusEnum::APPROVED,
    ]);

    $this->postJson($this->baseUrl('/user/products/'.$product->id.'/comments'), [
        'body' => 'Reply attempt',
        'parent_id' => $parent->id,
    ])->assertStatus(400)->assertJsonPath('code', 'catalog.product_comment.invalid_parent');
});
