<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Models\AttributeTranslation;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Models\AttributeValueTranslation;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;
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

it('shows the product attributes ordered by position with localized titles', function () {
    $languageId = defaultLanguage()->id;
    $group = AttributeGroup::query()->create();

    $brand = Attribute::query()->create([
        'group_id' => $group->id,
        'code' => 'brand',
        'data_type' => AttributeTypeEnum::SELECT,
        'position' => 2,
    ]);
    AttributeTranslation::query()->create([
        'attribute_id' => $brand->id,
        'language_id' => $languageId,
        'title' => 'Brand',
    ]);
    $urbanEdge = AttributeValue::query()->create(['attribute_id' => $brand->id]);
    AttributeValueTranslation::query()->create([
        'attribute_value_id' => $urbanEdge->id,
        'language_id' => $languageId,
        'title' => 'UrbanEdge',
    ]);

    $material = Attribute::query()->create([
        'group_id' => $group->id,
        'code' => 'material',
        'data_type' => AttributeTypeEnum::TEXT,
        'position' => 1,
    ]);
    AttributeTranslation::query()->create([
        'attribute_id' => $material->id,
        'language_id' => $languageId,
        'title' => 'Material',
    ]);

    $product = publicProduct('attributed-product');
    $product->attributeValues()->createMany([
        ['attribute_id' => $brand->id, 'attribute_value_id' => $urbanEdge->id],
        ['attribute_id' => $material->id, 'text_value' => 'Cotton'],
    ]);

    $this->getJson($this->baseUrl('/public/products/'.$product->id))
        ->assertOk()
        ->assertJsonCount(2, 'data.attributes')
        ->assertJsonPath('data.attributes.0.code', 'material')
        ->assertJsonPath('data.attributes.0.title', 'Material')
        ->assertJsonPath('data.attributes.0.value', 'Cotton')
        ->assertJsonPath('data.attributes.1.code', 'brand')
        ->assertJsonPath('data.attributes.1.title', 'Brand')
        ->assertJsonPath('data.attributes.1.value', 'UrbanEdge');
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
