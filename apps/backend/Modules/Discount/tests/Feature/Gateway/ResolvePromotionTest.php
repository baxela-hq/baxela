<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\Core\Contracts\Gateways\Discount\DTOs\ProductPromotion;
use Modules\Core\Support\Money;
use Modules\Discount\Gateways\PromotionGateway;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionCategorySchema;
use Modules\Discount\Schemas\Promotion\PromotionProductSchema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Schemas\Promotion\ScopeTypeEnum;

uses(RefreshDatabase::class);

/**
 * Resolve via the gateway with a caller-supplied category map — mirroring
 * how Catalog invokes it (Discount itself never reaches into Catalog).
 *
 * @param  array<int, int[]>  $categoryMap
 */
function resolvePromotions(array $productIds, array $categoryMap = []): Collection
{
    return app(PromotionGateway::class)
        ->resolveForProducts($productIds, collect($categoryMap));
}

function scopedPromotion(array $overrides = [], array $productIds = [], array $categoryIds = []): Promotion
{
    $promotion = Promotion::factory()->create(array_merge(
        [PromotionSchema::SCOPE => ScopeTypeEnum::SPECIFIC->value],
        $overrides,
    ));

    foreach ($productIds as $productId) {
        DB::table(PromotionProductSchema::TABLE)->insert([
            PromotionProductSchema::PROMOTION_ID => $promotion->id,
            PromotionProductSchema::PRODUCT_ID => $productId,
        ]);
    }

    foreach ($categoryIds as $categoryId) {
        DB::table(PromotionCategorySchema::TABLE)->insert([
            PromotionCategorySchema::PROMOTION_ID => $promotion->id,
            PromotionCategorySchema::CATEGORY_ID => $categoryId,
        ]);
    }

    return $promotion;
}

it('resolves nothing without promotions', function () {
    expect(resolvePromotions([1, 2, 3]))->toBeEmpty();
});

it('applies a store-wide promotion to every product', function () {
    $promotion = Promotion::factory()->create([PromotionSchema::SCOPE => ScopeTypeEnum::ALL->value]);

    $resolved = resolvePromotions([7, 8]);

    expect($resolved)->toHaveCount(2)
        ->and($resolved->get(7))->toBeInstanceOf(ProductPromotion::class)
        ->and($resolved->get(7)->promotion_id)->toBe((int) $promotion->id)
        ->and($resolved->get(8)->promotion_id)->toBe((int) $promotion->id);
});

it('matches a specific promotion by product pivot, category pivot, or both (union)', function () {
    $promotion = scopedPromotion([], [11], [5]);

    $byProduct = resolvePromotions([11], []);
    $byCategory = resolvePromotions([12], [12 => [5]]);
    $unmatched = resolvePromotions([13], [13 => [6]]);

    expect($byProduct->get(11)->promotion_id)->toBe((int) $promotion->id)
        ->and($byCategory->get(12)->promotion_id)->toBe((int) $promotion->id)
        ->and($unmatched)->toBeEmpty();
});

it('prefers product over category over store-wide, regardless of priority', function () {
    // The store-wide rule would give the bigger cut; specificity still wins
    Promotion::factory()->create([
        PromotionSchema::SCOPE => ScopeTypeEnum::ALL->value,
        PromotionSchema::VALUE => 50,
        PromotionSchema::PRIORITY => 100,
    ]);
    $categoryRule = scopedPromotion([PromotionSchema::VALUE => 20, PromotionSchema::PRIORITY => 1], [], [5]);
    $productRule = scopedPromotion([PromotionSchema::VALUE => 12, PromotionSchema::PRIORITY => 0], [30], []);

    $resolved = resolvePromotions([30, 31], [30 => [5], 31 => [5]]);

    expect($resolved->get(30)->promotion_id)->toBe((int) $productRule->id)
        ->and($resolved->get(31)->promotion_id)->toBe((int) $categoryRule->id);
});

it('breaks ties inside a rung by priority desc then lower id', function () {
    $lowPriority = scopedPromotion([PromotionSchema::PRIORITY => 0], [50], []);
    $highPriority = scopedPromotion([PromotionSchema::PRIORITY => 10], [50], []);
    $equalHigh = scopedPromotion([PromotionSchema::PRIORITY => 10], [50], []);

    $resolved = resolvePromotions([50]);

    expect($resolved->get(50)->promotion_id)->toBe((int) $highPriority->id)
        ->and((int) $highPriority->id)->toBeLessThan((int) $equalHigh->id)
        ->and($lowPriority->id)->not->toBe($resolved->get(50)->promotion_id);
});

it('breaks equal-specificity equal-priority category matches deterministically', function () {
    $first = scopedPromotion([PromotionSchema::PRIORITY => 5], [], [5]);
    $second = scopedPromotion([PromotionSchema::PRIORITY => 5], [], [5]);

    $resolved = resolvePromotions([60], [60 => [5]]);

    expect($resolved->get(60)->promotion_id)->toBe(min((int) $first->id, (int) $second->id));
});

it('honours the inclusive UTC window boundaries and the active flag', function (array $overrides, bool $expected) {
    // Frozen on a whole second so the stored datetime and the resolver's
    // now() are the exact same instant (MySQL datetime is second-granular);
    // with real time, "ends exactly now" would already be microseconds
    // past by the time the gateway queries
    Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00'));

    Promotion::factory()->create(array_merge(
        [PromotionSchema::SCOPE => ScopeTypeEnum::ALL->value],
        $overrides,
    ));

    expect(resolvePromotions([1])->isNotEmpty())->toBe($expected);
})->with([
    'starts exactly now (inclusive)' => [[PromotionSchema::STARTS_AT => '2026-10-09 12:00:00'], true],
    'ends exactly now (inclusive)' => [[PromotionSchema::ENDS_AT => '2026-10-09 12:00:00'], true],
    'starts in the next second' => [[PromotionSchema::STARTS_AT => '2026-10-09 12:00:01'], false],
    'ended the previous second' => [[PromotionSchema::ENDS_AT => '2026-10-09 11:59:59'], false],
    'inactive' => [[PromotionSchema::IS_ACTIVE => false], false],
]);

it('computes the actual applied discount from one calculation', function (string $type, string $value, string $base, int $effective, int $discount) {
    Promotion::factory()->create([
        PromotionSchema::SCOPE => ScopeTypeEnum::ALL->value,
        PromotionSchema::TYPE => $type,
        PromotionSchema::VALUE => $value,
    ]);

    $promoted = resolvePromotions([1])->get(1)->applyTo($base);

    expect($promoted->effective_minor)->toBe($effective)
        ->and($promoted->discount_minor)->toBe($discount)
        // Invariant: the split always sums back to the base price
        ->and($promoted->effective_minor + $promoted->discount_minor)->toBe(Money::fromDecimal($base));
})->with([
    // 100.01 × 15% = 15.0015 → discount 15.00 (half-up), effective 85.01
    'percent half-up' => ['percent', '15.00', '100.01', 8501, 1500],
    // 40 off a 30 base → discount 30, effective 0 (free item, never negative)
    'fixed clamped at base' => ['fixed', '40.00', '30.00', 0, 3000],
    'fixed under base' => ['fixed', '10.50', '30.00', 1950, 1050],
]);

it('splits each variant base independently under the same promotion', function () {
    Promotion::factory()->create([
        PromotionSchema::SCOPE => ScopeTypeEnum::ALL->value,
        PromotionSchema::TYPE => 'percent',
        PromotionSchema::VALUE => '20.00',
        PromotionSchema::ENDS_AT => now()->addDays(3),
    ]);

    $dto = resolvePromotions([1])->get(1);
    $first = $dto->applyTo('100.00');
    $second = $dto->applyTo('49.99'); // ×20% = 9.998 → 10.00 half-up

    expect($first->effective_minor)->toBe(8000)->and($first->discount_minor)->toBe(2000)
        ->and($second->effective_minor)->toBe(3999)->and($second->discount_minor)->toBe(1000)
        ->and($dto->ends_at)->not->toBeNull();
});

it('resolves for a batch in one call with per-product winners', function () {
    $productRule = scopedPromotion([], [101], []);
    $categoryRule = scopedPromotion([], [], [7]);
    Promotion::factory()->create([PromotionSchema::SCOPE => ScopeTypeEnum::ALL->value]);

    $resolved = resolvePromotions([101, 102, 103], [102 => [7], 103 => [999]]);

    expect($resolved->get(101)->promotion_id)->toBe((int) $productRule->id)
        ->and($resolved->get(102)->promotion_id)->toBe((int) $categoryRule->id)
        // Unmatched by any specific rule → store-wide fallback wins
        ->and($resolved->get(103))->toBeInstanceOf(ProductPromotion::class);
});
