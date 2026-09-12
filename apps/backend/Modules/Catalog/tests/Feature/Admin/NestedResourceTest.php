<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Tests\Feature\HelperTrait;
use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * One representative of the nested admin resource family
 * (options → values, attributes → values): the parent is created plainly,
 * the child hangs off /{id}/values.
 */
it('creates an option and nests option values under it', function () {
    $this->actingAs($this->superAdminUser());
    defaultLanguage();

    $optionId = $this->postJson($this->baseUrl('/admin/options'), [
        'position' => 1,
        'translations' => [['language' => 'en', 'title' => 'Color', 'slug' => 'color']],
    ])->assertCreated()->json('data.id');

    expect(Option::query()->find($optionId)->translations()->first()->title)->toBe('Color');

    $this->postJson($this->baseUrl('/admin/options/'.$optionId.'/values'), [
        'position' => 1,
        'translations' => [['language' => 'en', 'title' => 'Red', 'slug' => 'red']],
    ])->assertCreated();

    $this->postJson($this->baseUrl('/admin/options/'.$optionId.'/values'), [
        'position' => 2,
        'translations' => [['language' => 'en', 'title' => 'Blue', 'slug' => 'blue']],
    ])->assertCreated();

    $response = $this->getJson($this->baseUrl('/admin/options/'.$optionId.'/values'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $titles = collect($response->json('data'))->pluck('translations')->flatten(1)->pluck('title');

    expect($titles)->toContain('Red', 'Blue')
        ->and(OptionValue::query()->where('option_id', $optionId)->count())->toBe(2);
});

it('rejects nested value creation for an unknown option', function () {
    $this->actingAs($this->superAdminUser());
    defaultLanguage();

    $this->postJson($this->baseUrl('/admin/options/999999/values'), [
        'position' => 1,
        'translations' => [['language' => 'en', 'title' => 'Red', 'slug' => 'red']],
    ])->assertStatus(404);
});
