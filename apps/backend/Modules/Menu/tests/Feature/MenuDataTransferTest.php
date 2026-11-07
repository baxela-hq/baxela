<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema;
use Modules\Menu\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports menus with parents-first links through the module endpoint', function () {
    $en = $this->defaultLanguage();
    $seeded = $this->seedTransferMenu((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedMenuDataFile();

    expect($file['module'])->toBe('menu')
        ->and(array_column($file['sections'], 'entity'))->toBe(['menus', 'menu-links'])
        ->and($file['sections'][1]['rows'][0]['source_id'])->toBe($seeded['parent']->id)
        ->and($file['sections'][1]['rows'][1]['payload']['parent_id'])->toBe($seeded['parent']->id);
});

it('round-trips menus via the module endpoint', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferMenu((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedMenuDataFile();

    DB::table(MenuLinkTranslationSchema::TABLE)->delete();
    DB::table(MenuLinkSchema::TABLE)->delete();
    DB::table(MenuTranslationSchema::TABLE)->delete();
    DB::table(MenuSchema::TABLE)->delete();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(3)
        ->and(Menu::query()->count())->toBe(1)
        ->and(MenuLink::query()->count())->toBe(2);

    // The tree survived: the child hangs off the remapped parent.
    $menu = Menu::query()->first();
    $parent = MenuLink::query()->whereNull(MenuLinkSchema::PARENT_ID)->first();
    $child = MenuLink::query()->whereNotNull(MenuLinkSchema::PARENT_ID)->first();
    expect($menu->location->value)->toBe('header')
        ->and($child->{MenuLinkSchema::PARENT_ID})->toBe($parent->id)
        ->and($child->{MenuLinkSchema::MENU_ID})->toBe($menu->id);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(401);
});
