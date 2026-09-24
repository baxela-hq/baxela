<?php

use Modules\Core\Support\Money;

it('converts decimal strings to minor units', function (string $decimal, int $expected) {
    expect(Money::fromDecimal($decimal))->toBe($expected);
})->with([
    'two fraction digits' => ['100.10', 10010],
    'no fraction' => ['150', 15000],
    'zero' => ['0', 0],
    'one cent' => ['0.01', 1],
    'single fraction digit is padded' => ['100.1', 10010],
    'large amount' => ['99999999.99', 9999999999],
]);

it('rejects malformed decimal amounts', function (string $decimal) {
    expect(fn () => Money::fromDecimal($decimal))->toThrow(InvalidArgumentException::class);
})->with([
    'negative' => ['-5.00'],
    'too many fraction digits' => ['1.234'],
    'not numeric' => ['abc'],
    'dangling dot' => ['1.'],
    'empty' => [''],
]);

it('converts minor units to decimal strings with exact precision', function (int $minor, string $expected) {
    expect(Money::toDecimal($minor))->toBe($expected);
})->with([
    'padding' => [10010, '100.10'],
    'zero' => [0, '0.00'],
    'cents only' => [5, '0.05'],
    'whole' => [15000, '150.00'],
]);

it('rejects negative minor units on output', function () {
    expect(fn () => Money::toDecimal(-1))->toThrow(InvalidArgumentException::class);
});

it('rounds percent application half-up exactly once', function (int $minor, string $percent, int $expected) {
    expect(Money::applyPercent($minor, $percent))->toBe($expected);
})->with([
    // 100.01 × 15% = 15.0015 → 15.00 (review-pinned case)
    'review pin' => [10001, '15.00', 1500],
    // 10.10 × 25% = 2.525 → 2.53 (half rounds up)
    'half up' => [1010, '25.00', 253],
    'whole result' => [20000, '15.00', 3000],
    // 0.01 × 1% = 0.0001 → 0.00 (zero discount is a valid amount)
    'rounds to zero' => [1, '1.00', 0],
    'zero subtotal' => [0, '50.00', 0],
    'fractional percent' => [20000, '12.50', 2500],
]);

it('picks the smallest minor amount', function () {
    expect(Money::min(3000, 5000, 2000))->toBe(2000);
});

it('round-trips decimal strings through minor units', function (string $decimal) {
    expect(Money::toDecimal(Money::fromDecimal($decimal)))->toBe($decimal);
})->with([
    '100.10', '0.01', '150.00', '0.00',
]);
