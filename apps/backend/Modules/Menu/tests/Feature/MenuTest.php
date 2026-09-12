<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Menu\Models\Menu;
use Modules\Menu\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function menuPayload(string $location = 'header'): array
{
    return [
        'location' => $location,
        'is_active' => true,
        'translations' => [[
            'language' => 'en',
            'title' => 'Main Menu',
            'description' => null,
        ]],
    ];
}

function linkPayload(?int $parentId, string $url): array
{
    return [
        'parent_id' => $parentId,
        'url' => $url,
        'target' => '_self',
        'position' => 1,
        'translations' => [[
            'language' => 'en',
            'title' => 'A link',
            'description' => null,
        ]],
    ];
}

it('creates a menu with nested links and serves the public tree', function () {
    $this->actingAs($this->superAdminUser());
    TestCase::defaultLanguage();

    $menuId = $this->postJson($this->baseUrl('/admin/menus'), menuPayload())
        ->assertCreated()
        ->json('data.id');

    $parentId = $this->postJson($this->baseUrl('/admin/menus/'.$menuId.'/links'), linkPayload(null, '/shop'))
        ->assertCreated()
        ->json('data.id');

    $this->postJson($this->baseUrl('/admin/menus/'.$menuId.'/links'), linkPayload($parentId, '/shop/shoes'))
        ->assertCreated();

    $this->getJson($this->baseUrl('/public/menus/header'))
        ->assertOk()
        ->assertJsonPath('data.location', 'header')
        ->assertJsonCount(1, 'data.links')
        ->assertJsonCount(1, 'data.links.0.children');

    expect(Menu::query()->find($menuId)->links()->count())->toBe(2);
});

it('returns 404 for an unknown menu location', function () {
    $this->getJson($this->baseUrl('/public/menus/sidebar'))
        ->assertStatus(404)
        ->assertJsonPath('code', 'http.404');
});

it('hides inactive menus from the public tree', function () {
    TestCase::defaultLanguage();

    $menu = Menu::factory()->create([
        'location' => 'footer',
        'is_active' => false,
    ]);
    $menu->translations()->create([
        'language_id' => TestCase::defaultLanguage()->id,
        'title' => 'Footer Menu',
    ]);

    $this->getJson($this->baseUrl('/public/menus/footer'))
        ->assertStatus(404);
});
