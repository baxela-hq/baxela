<?php

use Modules\Core\Support\Money;

it('converts with the identity rate', function () {
    expect(Money::convert(10010, '1'))->toBe(10010)
        ->and(Money::convert(10010, '1.00000000'))->toBe(10010);
});

it('converts between equal precisions with half-up rounding', function () {
    // 100.10 × 1.085 = 108.6085 → 108.61
    expect(Money::convert(10010, '1.085'))->toBe(10861)
        // 10.05 × 0.5 = 5.025 → 5.03 (half-up at the boundary)
        ->and(Money::convert(1005, '0.5'))->toBe(503)
        // 10.04 × 0.5 = 5.02 → 5.02
        ->and(Money::convert(1004, '0.5'))->toBe(502);
});

it('converts across precisions', function () {
    // 19.99 USD (2dp) → IRR (0dp) at 42000 per USD:
    // 19.99 × 42000 = 839'580 exactly
    expect(Money::convert(1999, '42000', 2, 0))->toBe(839580)
        // 839'580 IRR (0dp) back to 2dp at 1/42000 (rounded to 8 digits)
        // → 839'580 × 0.00002381 = 19.99039... → 19.99
        ->and(Money::convert(839580, '0.00002381', 0, 2))->toBe(1999);
});

it('uses the full 8-digit rate storage precision', function () {
    // 50.00 × 1.00000001 = 50.0000005 → half-up at 2dp → 50.00
    expect(Money::convert(5000, '1.00000001'))->toBe(5000)
        // 100'000.00 × 1.00000001 = 100'000.001 → 100'000.00
        ->and(Money::convert(10000000, '1.00000001'))->toBe(10000000)
        // 1'000'000.00 × 1.00000005 = 1'000'000.05 minor → 100'000'005
        ->and(Money::convert(100000000, '1.00000005'))->toBe(100000005);
});

it('rejects malformed rates and negative amounts', function (int $minor, string $rate) {
    expect(fn () => Money::convert($minor, $rate))->toThrow(InvalidArgumentException::class);
})->with([
    'negative amount' => [-1, '1'],
    'rate with sign' => [100, '-1.5'],
    'rate with too many digits' => [100, '1.123456789'],
    'rate as words' => [100, 'free'],
    'rate without fraction allowed' => [100, '1.'],
]);

it('keeps a zero result representable', function () {
    // 0.01 × 0.5 = 0.005 → half-up → 0.01; tiny values may also floor to 0
    expect(Money::convert(1, '0.5'))->toBe(1)
        ->and(Money::convert(1, '0.004'))->toBe(0);
});
