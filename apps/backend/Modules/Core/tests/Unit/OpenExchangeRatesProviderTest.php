<?php

use Modules\Core\Contracts\Gateways\Fx\FxProviderException;
use Modules\Core\Support\Fx\OpenExchangeRatesProvider;

/**
 * The raw-slice parsing seam (review F16): two-tier policy — structural
 * document failures reject the WHOLE snapshot; individual rate values
 * are passed through as exact decimal strings for later per-rate
 * validation. Numeric precision must survive without any float
 * round-trip.
 */
function fxProvider(): OpenExchangeRatesProvider
{
    return new OpenExchangeRatesProvider('test-key', 'https://openexchangerates.test/api');
}

it('preserves exact numeric precision as strings', function () {
    $body = '{"disclaimer":"x","license":"y","timestamp":1700000000,"base":"USD",'
        .'"rates":{"AED":3.6725,"EUR":0.923456789,"GBP":0.79,"JPY":149.123456789012}}';

    $snapshot = fxProvider()->parse($body);

    expect($snapshot->baseCode)->toBe('USD')
        ->and($snapshot->timestamp)->toBe(1700000000)
        ->and($snapshot->rates['EUR'])->toBe('0.923456789')
        ->and($snapshot->rates['JPY'])->toBe('149.123456789012')
        ->and($snapshot->rates['GBP'])->toBe('0.79');
});

it('rejects a malformed json document', function () {
    fxProvider()->parse('{"base": "USD", "timestamp": 1, "rates": {');
})->throws(FxProviderException::class);

it('rejects documents missing base, timestamp or rates', function () {
    expect(fn () => fxProvider()->parse('{"timestamp":1,"rates":{"EUR":1}}'))->toThrow(FxProviderException::class)
        ->and(fn () => fxProvider()->parse('{"base":"USD","rates":{"EUR":1}}'))->toThrow(FxProviderException::class)
        ->and(fn () => fxProvider()->parse('{"base":"USD","timestamp":1}'))->toThrow(FxProviderException::class)
        ->and(fn () => fxProvider()->parse('{"base":"USD","timestamp":1,"rates":{}}'))->toThrow(FxProviderException::class);
});

it('rejects nested or string rate values', function () {
    expect(fn () => fxProvider()->parse('{"base":"USD","timestamp":1,"rates":{"EUR":{"a":1}}}'))
        ->toThrow(FxProviderException::class)
        ->and(fn () => fxProvider()->parse('{"base":"USD","timestamp":1,"rates":{"EUR":"1.2"}}'))
        ->toThrow(FxProviderException::class);
});

it('rejects unexpected currency code shapes', function () {
    expect(fn () => fxProvider()->parse('{"base":"USD","timestamp":1,"rates":{"eur":1}}'))
        ->toThrow(FxProviderException::class)
        ->and(fn () => fxProvider()->parse('{"base":"USD","timestamp":1,"rates":{"EURO":1}}'))
        ->toThrow(FxProviderException::class)
        ->and(fn () => fxProvider()->parse('{"base":"USD","timestamp":1,"rates":{"E-U":1}}'))
        ->toThrow(FxProviderException::class);
});

it('rejects duplicate rate keys as an ambiguous document', function () {
    // json_decode silently keeps the last duplicate — the raw-slice
    // extractor must disagree with it and reject the document.
    fxProvider()->parse('{"base":"USD","timestamp":1,"rates":{"EUR":1,"EUR":2}}');
})->throws(FxProviderException::class);

it('keeps exponent-notation values for later per-rate skipping', function () {
    // Structurally valid JSON numbers — the formatter rejects them, not
    // the document parser (F16: document vs per-rate failure split).
    $snapshot = fxProvider()->parse('{"base":"USD","timestamp":1,"rates":{"EUR":1.5e-7,"GBP":0.9}}');

    expect($snapshot->rates['EUR'])->toBe('1.5e-7')
        ->and($snapshot->rates['GBP'])->toBe('0.9');
});

it('extracts rates only from the rates object', function () {
    // A numeric-looking value outside the rates object must never
    // become a rate.
    $body = '{"disclaimer":"license 12345","timestamp":1700000000,"base":"USD",'
        .'"rates":{"EUR":0.9}}';

    $snapshot = fxProvider()->parse($body);

    expect($snapshot->rates)->toBe(['EUR' => '0.9']);
});
