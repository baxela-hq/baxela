<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Exceptions\Tax\TaxConfigurationException;
use Modules\Tax\Gateways\TaxGateway;
use Modules\Tax\Tests\Feature\HelperTrait;
use Modules\Tax\Support\TaxMath;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function taxItem(int $variantId, int $amountMinor, ?int $classId = null): \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationItem
{
    return new \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationItem(
        variant_id: $variantId,
        amount_minor: $amountMinor,
        tax_class_id: $classId,
    );
}

it('parses decimal rate strings to exact integer rate-units', function (string $decimal, int $units) {
    expect(TaxMath::rateUnits($decimal))->toBe($units);
})->with([
    ['0', 0],
    ['8.5', 85000],
    ['8.50', 85000],
    ['8.5000', 85000],
    ['5.125', 51250],
    ['5.1250', 51250],
    ['100', 1000000],
]);

it('computes per-line tax with half-up rounding on exact integers', function (int $baseMinor, int $rateUnits, int $expected) {
    expect(TaxMath::taxOf($baseMinor, $rateUnits))->toBe($expected);
})->with([
    // 5% of 100.00
    [10000, 50000, 500],
    // reviewer's example: 5.125% of 10.01 → 51.8 → 51 minor units
    [1001, 51250, 51],
    // 5% of 9.99 → 49.95 → rounds up
    [999, 50000, 50],
    [1000, 0, 0],
]);

it('rejects out-of-range inputs instead of overflowing', function () {
    TaxMath::taxOf(1, 1000001);
})->throws(InvalidArgumentException::class);

it('allocates a discount proportionally with deterministic remainders', function () {
    // exact shares
    expect(TaxMath::allocate(300, [1 => 1000, 2 => 2000]))->toBe([1 => 100, 2 => 200]);

    // 100 over 333/333/334: floors 33 each, the lost unit follows the
    // largest remainder (the 334 line); the result keeps input key order
    expect(TaxMath::allocate(100, [3 => 333, 1 => 333, 2 => 334]))->toBe([3 => 33, 1 => 33, 2 => 34]);

    // a genuine remainder tie resolves toward the lower key
    expect(TaxMath::allocate(1, [5 => 100, 2 => 100]))->toBe([5 => 0, 2 => 1]);

    // the allocation always sums to the (capped) discount
    $allocations = TaxMath::allocate(777, [10 => 12345, 20 => 6789, 30 => 999999]);
    expect(array_sum($allocations))->toBe(777);

    // a coupon beyond the subtotal clamps to it
    $capped = TaxMath::allocate(10000, [1 => 200, 2 => 100]);
    expect(array_sum($capped))->toBe(300)
        ->and($capped)->toBe([1 => 200, 2 => 100]);

    // zero discount and zero amounts allocate nothing
    expect(TaxMath::allocate(0, [1 => 500]))->toBe([1 => 0])
        ->and(TaxMath::allocate(500, []))->toBe([]);
});

it('resolves rates: found, default fallback, inactive zero', function () {
    $gateway = app(TaxGateway::class);

    $default = $this->taxClass(isDefault: true);
    $reduced = $this->taxClass();
    $inactiveOnly = $this->taxClass();
    $this->taxRate($default, 'US', '8.5');
    $this->taxRate($reduced, 'US', '4.0');
    // an inactive rate must never apply
    $this->taxRate($inactiveOnly, 'US', '2.0', isActive: false);

    $result = $gateway->calculateForItems(new \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput(
        items: [
            taxItem(1, 10000),                        // null class → default 8.5%
            taxItem(2, 10000, $reduced->id),          // explicit reduced class
            taxItem(3, 10000, $inactiveOnly->id),     // only an inactive rate → zero
        ],
        country_code: 'US',
    ));

    expect($result->lines[1]->tax_minor)->toBe(850)
        ->and($result->lines[1]->rate)->toBe('8.5000')
        ->and($result->lines[1]->tax_class_id)->toBe($default->id)
        ->and($result->lines[2]->tax_minor)->toBe(400)
        ->and($result->lines[2]->rate)->toBe('4.0000')
        ->and($result->lines[3]->tax_minor)->toBe(0)
        ->and($result->lines[3]->rate)->toBe('0.0000')
        ->and($result->total_minor)->toBe(1250);
});

it('rates a missing country as valid zero tax', function () {
    $default = $this->taxClass(isDefault: true);
    $this->taxRate($default, 'US', '8.5');

    $result = app(TaxGateway::class)->calculateForItems(new \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput(
        items: [taxItem(1, 10000)],
        country_code: 'DE',
    ));

    expect($result->lines[1]->tax_minor)->toBe(0)
        ->and($result->lines[1]->rate)->toBe('0.0000')
        ->and($result->lines[1]->tax_class_id)->toBe($default->id)
        ->and($result->total_minor)->toBe(0);
});

it('reduces the tax base by the allocated coupon discount', function () {
    // $100 line minus a $10 seller coupon → $90 taxed at 10% = $9.00
    $default = $this->taxClass(isDefault: true);
    $this->taxRate($default, 'US', '10.0');

    $result = app(TaxGateway::class)->calculateForItems(new \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput(
        items: [taxItem(1, 10000)],
        country_code: 'US',
        coupon_discount_minor: 1000,
    ));

    expect($result->lines[1]->tax_minor)->toBe(900);
});

it('taxes shipping only through the flagged shipping class', function () {
    $default = $this->taxClass(isDefault: true);
    $shipping = $this->taxClass(isShipping: true);
    $this->taxRate($default, 'US', '8.5');
    $this->taxRate($shipping, 'US', '8.5');

    $withRate = app(TaxGateway::class)->calculateForItems(new \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput(
        items: [taxItem(1, 10000)],
        country_code: 'US',
        shipping_cost_minor: 500,
    ));

    // invariant: total = Σ line taxes + shipping tax, shipping counted once
    expect($withRate->shipping_tax_minor)->toBe(43)
        ->and($withRate->shipping_rate)->toBe('8.5000')
        ->and($withRate->shipping_tax_class_id)->toBe($shipping->id)
        ->and($withRate->total_minor)->toBe(850 + 43);

    // no shipping rate for the country → untaxed shipping (valid config)
    $withoutRate = app(TaxGateway::class)->calculateForItems(new \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput(
        items: [taxItem(1, 10000)],
        country_code: 'DE',
        shipping_cost_minor: 500,
    ));

    expect($withoutRate->shipping_tax_minor)->toBe(0)
        ->and($withoutRate->shipping_rate)->toBeNull()
        ->and($withoutRate->total_minor)->toBe(0);
});

it('fails loudly on a dangling tax-class reference', function () {
    $this->taxClass(isDefault: true);

    app(TaxGateway::class)->calculateForItems(new \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput(
        items: [taxItem(1, 10000, 999999)],
        country_code: 'US',
    ));
})->throws(TaxConfigurationException::class, 'tax.configuration.invalid_class');

it('fails loudly when no default class exists', function () {
    $this->taxClass();

    app(TaxGateway::class)->calculateForItems(new \Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput(
        items: [taxItem(1, 10000)],
        country_code: 'US',
    ));
})->throws(TaxConfigurationException::class, 'tax.configuration.no_default');
