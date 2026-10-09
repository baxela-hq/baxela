<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionProductSchema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Schemas\Promotion\ScopeTypeEnum;
use Modules\Discount\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function promotionPayload(array $overrides = []): array
{
    return array_merge([
        PromotionSchema::NAME => 'Winter sale',
        PromotionSchema::SCOPE => 'all',
        PromotionSchema::TYPE => 'percent',
        PromotionSchema::VALUE => 20,
        PromotionSchema::STARTS_AT => null,
        PromotionSchema::ENDS_AT => null,
        PromotionSchema::PRIORITY => 0,
        PromotionSchema::IS_ACTIVE => true,
        PromotionSchema::PRODUCT_IDS => [],
        PromotionSchema::CATEGORY_IDS => [],
    ], $overrides);
}

it('lists promotions for a super admin with their scope selections', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    Promotion::factory()->count(2)->create([PromotionSchema::SCOPE => ScopeTypeEnum::ALL->value]);
    $specific = Promotion::factory()->create([PromotionSchema::SCOPE => ScopeTypeEnum::SPECIFIC->value]);
    $specific->syncScope([$product->id], [$category->id]);

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/promotions'))
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $row = collect($this->getJson($this->baseUrl('/admin/promotions'))->json('data'))
        ->firstWhere(PromotionSchema::ID, $specific->id);

    expect($row[PromotionSchema::PRODUCT_IDS])->toBe([$product->id])
        ->and($row[PromotionSchema::CATEGORY_IDS])->toBe([$category->id]);
});

it('creates a specific promotion and syncs its scope pivots', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl('/admin/promotions'), promotionPayload([
            PromotionSchema::SCOPE => 'specific',
            PromotionSchema::TYPE => 'fixed',
            PromotionSchema::VALUE => 15.5,
            PromotionSchema::PRIORITY => 10,
            PromotionSchema::STARTS_AT => '2026-10-01T00:00:00Z',
            PromotionSchema::ENDS_AT => '2026-10-31T23:59:59Z',
            PromotionSchema::PRODUCT_IDS => [$product->id],
            PromotionSchema::CATEGORY_IDS => [$category->id],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.'.PromotionSchema::PRIORITY, 10)
        ->assertJsonPath('data.'.PromotionSchema::PRODUCT_IDS, [$product->id]);

    expect(DB::table(PromotionProductSchema::TABLE)->count())->toBe(1);
});

it('shows and updates a promotion, replacing its scope wholesale', function () {
    $promotion = Promotion::factory()->create([PromotionSchema::SCOPE => ScopeTypeEnum::SPECIFIC->value]);
    $oldProduct = Product::factory()->create();
    $newProduct = Product::factory()->create();
    $promotion->syncScope([$oldProduct->id], []);

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/promotions/'.$promotion->id))
        ->assertOk()
        ->assertJsonPath('data.'.PromotionSchema::PRODUCT_IDS, [$oldProduct->id]);

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl('/admin/promotions/'.$promotion->id), promotionPayload([
            PromotionSchema::SCOPE => 'specific',
            PromotionSchema::NAME => 'Renamed',
            PromotionSchema::PRODUCT_IDS => [$newProduct->id],
        ]))
        ->assertOk()
        ->assertJsonPath('data.'.PromotionSchema::NAME, 'Renamed')
        ->assertJsonPath('data.'.PromotionSchema::PRODUCT_IDS, [$newProduct->id]);

    expect($promotion->refresh()->selectedProductIds()->all())->toBe([$newProduct->id]);
});

it('clears stale selections when the scope switches to all', function () {
    $product = Product::factory()->create();
    $promotion = Promotion::factory()->create([PromotionSchema::SCOPE => ScopeTypeEnum::SPECIFIC->value]);
    $promotion->syncScope([$product->id], []);

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl('/admin/promotions/'.$promotion->id), promotionPayload([
            PromotionSchema::SCOPE => 'all',
        ]))
        ->assertOk();

    expect($promotion->refresh()->selectedProductIds())->toBeEmpty();
});

it('deletes a promotion and cascades its pivots', function () {
    $product = Product::factory()->create();
    $promotion = Promotion::factory()->create([PromotionSchema::SCOPE => ScopeTypeEnum::SPECIFIC->value]);
    $promotion->syncScope([$product->id], []);

    $this->actingAs($this->superAdminUser())
        ->deleteJson($this->baseUrl('/admin/promotions/'.$promotion->id))
        ->assertNoContent();

    expect(Promotion::count())->toBe(0)
        ->and(DB::table(PromotionProductSchema::TABLE)->count())->toBe(0);
});

it('rejects invalid promotion configurations', function (array $overrides) {
    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl('/admin/promotions'), promotionPayload($overrides))
        ->assertStatus(422);
})->with([
    'percent above 100' => [[PromotionSchema::VALUE => 150]],
    'zero value' => [[PromotionSchema::VALUE => 0]],
    'ends before start' => [[PromotionSchema::STARTS_AT => '2026-10-10T00:00:00Z', PromotionSchema::ENDS_AT => '2026-10-01T00:00:00Z']],
    'negative priority' => [[PromotionSchema::PRIORITY => -1]],
    'unknown scope' => [[PromotionSchema::SCOPE => 'everything']],
    'specific without any selection' => [[PromotionSchema::SCOPE => 'specific', PromotionSchema::PRODUCT_IDS => [], PromotionSchema::CATEGORY_IDS => []]],
    'all with a product selection' => [[PromotionSchema::SCOPE => 'all', PromotionSchema::PRODUCT_IDS => [1]]],
    'nonexistent product id' => [[PromotionSchema::SCOPE => 'specific', PromotionSchema::PRODUCT_IDS => [999999]]],
    'nonexistent category id' => [[PromotionSchema::SCOPE => 'specific', PromotionSchema::CATEGORY_IDS => [999999]]],
]);

it('denies promotion management without admin permission', function () {
    $this->actingAs(User::factory()->create())
        ->getJson($this->baseUrl('/admin/promotions'))
        ->assertStatus(403);
});
