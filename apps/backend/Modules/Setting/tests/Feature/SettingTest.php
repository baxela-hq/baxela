<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Setting\Models\Setting;
use Modules\Setting\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exposes only the public settings publicly', function () {
    Setting::factory()->create(['name' => 'website_title', 'value' => 'Baxela Shop']);
    Setting::factory()->create(['name' => 'language_id', 'value' => '1']);

    $response = $this->getJson($this->baseUrl('/public/settings'))
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('website_title')
        ->not->toContain('language_id');
});

it('persists admin setting updates', function () {
    $this->actingAs($this->superAdminUser());
    Setting::factory()->create(['name' => 'website_title', 'value' => 'Old Title']);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'website_title', 'value' => 'New Title', 'translations' => null],
    ])->assertOk();

    expect(Setting::query()->where('name', 'website_title')->first()->value)->toBe('New Title');
});
