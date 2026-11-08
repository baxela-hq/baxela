<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Models\Country;
use Modules\Tax\Support\VatIdValidator;
use Modules\User\Models\Address;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function seedCountry(string $code): void
{
    Country::query()->firstOrCreate(
        ['code' => $code],
        ['code3' => $code.'X', 'name' => 'Country '.$code],
    );
}

function vatAddressPayload(array $overrides = []): array
{
    return array_merge([
        AddressSchema::TYPE => 'shipping',
        AddressSchema::FULL_NAME => 'Biz Customer',
        AddressSchema::PHONE => '+49 30 000',
        AddressSchema::ADDRESS_LINE => 'Torstraße 1',
        AddressSchema::CITY => 'Berlin',
        AddressSchema::POSTAL_CODE => '10119',
        AddressSchema::COUNTRY_CODE => 'DE',
        AddressSchema::IS_DEFAULT => false,
    ], $overrides);
}

it('validates VAT formats per country', function (string $country, string $vat, ?bool $expected) {
    expect((new VatIdValidator)->plausibleFormat($country, $vat))->toBe($expected);
})->with([
    'DE valid' => ['DE', 'DE123456789', true],
    'DE invalid checksum length' => ['DE', 'DE12345678', false],
    'DE normalized input' => ['DE', ' de 123-456 789 ', true],
    'prefix must match the country' => ['DE', 'FR1234567890', false],
    'Greek EL exception' => ['GR', 'EL123456789', true],
    'GR without EL prefix fails' => ['GR', 'GR123456789', false],
    'NL with branch digits' => ['NL', 'NL123456789B01', true],
    'unknown country claims nothing' => ['CH', 'CHE123456789', null],
]);

it('stores a valid VAT id normalized on the address', function () {
    seedCountry('DE');
    $this->actingAs(User::factory()->create());

    $this->postJson($this->baseUrl('/user/addresses'), vatAddressPayload([
        AddressSchema::VAT_ID => ' de 123-456 789 ',
    ]))->assertCreated();

    $address = Address::query()->first();

    expect($address->{AddressSchema::VAT_ID})->toBe('DE123456789');
});

it('rejects a malformed VAT id without echoing the submitted value', function () {
    seedCountry('DE');
    $this->actingAs(User::factory()->create());

    $response = $this->postJson($this->baseUrl('/user/addresses'), vatAddressPayload([
        AddressSchema::VAT_ID => 'DE123456789012',
    ]))->assertStatus(422);

    expect($response->getContent())->not->toContain('DE123456789012');
});

it('stores a VAT id without a validation claim for rule-less countries', function () {
    seedCountry('CH');
    $this->actingAs(User::factory()->create());

    $this->postJson($this->baseUrl('/user/addresses'), vatAddressPayload([
        AddressSchema::COUNTRY_CODE => 'CH',
        AddressSchema::VAT_ID => 'CHE-123.456.789',
    ]))->assertCreated();

    expect(Address::query()->first()->{AddressSchema::VAT_ID})->toBe('CHE123.456.789');
});
