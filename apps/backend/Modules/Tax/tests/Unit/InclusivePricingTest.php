<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationItem;
use Modules\Tax\Gateways\TaxGateway;
use Modules\Tax\Support\TaxMath;
use Modules\Tax\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function inclusiveInput(int $amount = 11000): TaxCalculationInput
{
    return new TaxCalculationInput(
        items: [new TaxCalculationItem(variant_id: 1, amount_minor: $amount)],
        country_code: 'DE',
        region_code: '',
        prices_include_tax: true,
    );
}

it('extracts net and tax from a gross amount with the pinned vector', function () {
    // 1001 minor at 10%: net ⌊1001×10^6 ÷ 1.1×10^6⌋ = 910, tax = 91 —
    // BOTH outputs asserted, not just their sum (gap-C1b D3).
    $split = TaxMath::extractInclusive(1001, TaxMath::rateUnits('10.0000'));

    expect($split['net_minor'])->toBe(910)
        ->and($split['tax_minor'])->toBe(91)
        ->and($split['net_minor'] + $split['tax_minor'])->toBe(1001);
});

it('satisfies the line invariant gross_after = net_after + tax_after with discounts', function () {
    $class = $this->taxClass(isDefault: true);
    $this->taxRate($class, 'DE', '19.0000');

    // 3 lines, 50.00 each gross, a 30.00 coupon allocated across them.
    $input = new TaxCalculationInput(
        items: [
            new TaxCalculationItem(variant_id: 1, amount_minor: 5000),
            new TaxCalculationItem(variant_id: 2, amount_minor: 5000),
            new TaxCalculationItem(variant_id: 3, amount_minor: 5000),
        ],
        country_code: 'DE',
        region_code: '',
        coupon_discount_minor: 3000,
        prices_include_tax: true,
    );

    $result = app(TaxGateway::class)->calculateForItems($input);

    $grossAfter = [1 => 4000, 2 => 4000, 3 => 4000]; // largest-remainder: even split

    foreach ($grossAfter as $variantId => $gross) {
        // The invariant: the extracted tax never exceeds the after-discount
        // gross, and net + tax reconstructs it exactly — verified via the
        // same math the gateway used (extractInclusive).
        $split = TaxMath::extractInclusive($gross, TaxMath::rateUnits('19.0000'));

        expect($split['net_minor'] + $split['tax_minor'])->toBe($gross)
            ->and($result->lines[$variantId]->tax_minor)->toBe($split['tax_minor']);
    }

    expect($result->prices_contain_tax)->toBeTrue();
});

it('extracts shipping tax through its class rate in inclusive mode', function () {
    $goods = $this->taxClass(isDefault: true);
    $shipping = $this->taxClass(isShipping: true);
    $this->taxRate($goods, 'DE', '19.0000');
    $this->taxRate($shipping, 'DE', '7.0000');

    $input = new TaxCalculationInput(
        items: [new TaxCalculationItem(variant_id: 1, amount_minor: 10000)],
        country_code: 'DE',
        region_code: '',
        shipping_cost_minor: 500,
        prices_include_tax: true,
    );

    $result = app(TaxGateway::class)->calculateForItems($input);

    $expected = TaxMath::extractInclusive(500, TaxMath::rateUnits('7.0000'));

    expect($result->shipping_tax_minor)->toBe($expected['tax_minor'])
        ->and($result->shipping_rate)->toBe('7.0000');
});

it('keeps a zero-rated inclusive line at zero tax with the mode still snapshotted', function () {
    $class = $this->taxClass(isDefault: true);
    // No rate for FR — valid zero configuration.
    $input = new TaxCalculationInput(
        items: [new TaxCalculationItem(variant_id: 1, amount_minor: 10000)],
        country_code: 'FR',
        region_code: '',
        prices_include_tax: true,
    );

    $result = app(TaxGateway::class)->calculateForItems($input);

    expect($result->total_minor)->toBe(0)
        ->and($result->prices_contain_tax)->toBeTrue();
});

it('keeps the exclusive path bit-for-bit unchanged (half-up, flag false)', function () {
    $class = $this->taxClass(isDefault: true);
    $this->taxRate($class, 'DE', '19.0000');

    $exclusive = app(TaxGateway::class)->calculateForItems(new TaxCalculationInput(
        items: [new TaxCalculationItem(variant_id: 1, amount_minor: 10001)],
        country_code: 'DE',
        region_code: '',
    ));

    // 10001 × 0.19 = 1900.19 → half-up 1900 minor.
    expect($exclusive->lines[1]->tax_minor)->toBe(1900)
        ->and($exclusive->prices_contain_tax)->toBeFalse();
});

it('splits refunds on the frozen ratio with full-refund exactness', function () {
    // Frozen line: gross 11900 at 19% → net 10000, tax 1900.
    $frozen = TaxMath::extractInclusive(11900, TaxMath::rateUnits('19.0000'));
    expect($frozen)->toBe(['net_minor' => 10000, 'tax_minor' => 1900]);

    // Partial refund of 5000 gross: net ⌊5000×10000/11900⌋ = 4201, tax 799.
    $partial = TaxMath::splitInclusiveRefund(5000, $frozen['net_minor'], 11900);

    expect($partial['net_minor'] + $partial['tax_minor'])->toBe(5000)
        ->and($partial['net_minor'])->toBe(4201)
        ->and($partial['tax_minor'])->toBe(799);

    // Full refund returns the EXACT frozen amounts — no drift.
    $full = TaxMath::splitInclusiveRefund(11900, $frozen['net_minor'], 11900);

    expect($full)->toBe(['net_minor' => 10000, 'tax_minor' => 1900]);
});
