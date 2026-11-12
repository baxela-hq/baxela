<?php

use Modules\Core\Support\Fx\FxRateFormatter;

/**
 * FxRateFormatter contract (reviews F2/F11/F20): pure string
 * arithmetic — no float anywhere — half-up rounding to 8dp AFTER an
 * arbitrary-precision input, validation of the ROUNDED result against
 * decimal(16,8), input-safety bound applied before any work.
 */
it('passes through rates with up to 8 fraction digits', function () {
    expect(FxRateFormatter::format('0.92345678'))->toBe('0.92345678')
        ->and(FxRateFormatter::format('12.3'))->toBe('12.30000000')
        ->and(FxRateFormatter::format('149'))->toBe('149.00000000');
});

it('rounds half-up from arbitrary precision', function () {
    // 9th fraction digit 5 → up
    expect(FxRateFormatter::format('0.123456785'))->toBe('0.12345679')
        // 9th digit 4 → down (truncate)
        ->and(FxRateFormatter::format('0.123456784'))->toBe('0.12345678')
        // deep precision, half-up at the 9th digit
        ->and(FxRateFormatter::format('600000.123456789'))->toBe('600000.12345679')
        // carry inside the fraction
        ->and(FxRateFormatter::format('1.999999995'))->toBe('2.00000000');
});

it('rejects values that round to zero', function () {
    // 9th digit 1 → rounds down to 0.00000000 (F11 edge)
    expect(FxRateFormatter::format('0.000000001'))->toBeNull()
        ->and(FxRateFormatter::format('0'))->toBeNull()
        ->and(FxRateFormatter::format('0.000000000'))->toBeNull();
});

it('keeps a half-up edge that rounds away from zero', function () {
    expect(FxRateFormatter::format('0.000000005'))->toBe('0.00000001');
});

it('rejects rounded results that overflow decimal(16,8)', function () {
    // 8 integer digits is the maximum — rounding must not carry beyond
    expect(FxRateFormatter::format('99999999.999999995'))->toBeNull()
        ->and(FxRateFormatter::format('100000000'))->toBeNull()
        // exactly at the boundary stays valid
        ->and(FxRateFormatter::format('99999999.9'))->toBe('99999999.90000000');
});

it('rejects malformed, signed and exponent tokens', function () {
    expect(FxRateFormatter::format('1e-5'))->toBeNull()
        ->and(FxRateFormatter::format('-0.5'))->toBeNull()
        ->and(FxRateFormatter::format('+0.5'))->toBeNull()
        ->and(FxRateFormatter::format('1,5'))->toBeNull()
        ->and(FxRateFormatter::format('abc'))->toBeNull()
        ->and(FxRateFormatter::format('1.2.3'))->toBeNull()
        ->and(FxRateFormatter::format(''))->toBeNull();
});

it('applies the input-safety bound before any arithmetic', function () {
    expect(FxRateFormatter::format(str_repeat('9', 40)))->toBeNull()
        ->and(FxRateFormatter::format(str_repeat('1', 32).'.5'))->toBeNull()
        // 32 chars exactly is the generous bound — this one fails on
        // integer-digit validation, not on the token bound
        ->and(FxRateFormatter::format(str_repeat('1', 24).'.'.'12345678'))->toBeNull();
});

it('is deterministic', function () {
    $first = FxRateFormatter::format('0.123456785');
    $second = FxRateFormatter::format('0.123456785');

    expect($first)->toBe($second);
});

it('flags exponent notation for per-rate skipping', function () {
    expect(FxRateFormatter::isExponentNotation('1.5e-7'))->toBeTrue()
        ->and(FxRateFormatter::isExponentNotation('0.9234'))->toBeFalse();
});
