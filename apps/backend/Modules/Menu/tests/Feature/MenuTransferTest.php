<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

function wipeMenuTables(): void
{
    DB::table(MenuLinkTranslationSchema::TABLE)->delete();
    DB::table(MenuLinkSchema::TABLE)->delete();
    DB::table(MenuTranslationSchema::TABLE)->delete();
    DB::table(MenuSchema::TABLE)->delete();
}

it('exports menus and parents-first links', function () {
    $en = $this->defaultLanguage();
    $seeded = $this->seedTransferMenu((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedMenuFile();

    expect($file['module'])->toBe('menu')
        ->and(array_column($file['sections'], 'entity'))->toBe(['menus', 'menu-links'])
        ->and($file['sections'][0]['rows'][0]['payload']['location'])->toBe('header')
        ->and($file['sections'][0]['rows'][0]['payload']['translations'][0]['language'])->toBe('en')
        // The child references its parent by source id.
        ->and($file['sections'][1]['rows'][0]['source_id'])->toBe($seeded['parent']->id)
        ->and($file['sections'][1]['rows'][1]['source_id'])->toBe($seeded['child']->id)
        ->and($file['sections'][1]['rows'][1]['payload']['parent_id'])->toBe($seeded['parent']->id);
});

it('round-trips: wipes menus and re-imports the export', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferMenu((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedMenuFile();

    wipeMenuTables();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('menu.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.created_count'))->toBe(3)
        ->and(Menu::query()->count())->toBe(1)
        ->and(MenuLink::query()->count())->toBe(2);

    // The tree survived: the child hangs off the remapped parent.
    $menu = Menu::query()->first();
    $parent = MenuLink::query()->whereNull(MenuLinkSchema::PARENT_ID)->first();
    $child = MenuLink::query()->whereNotNull(MenuLinkSchema::PARENT_ID)->first();
    expect($menu->location->value)->toBe('header')
        ->and($child->{MenuLinkSchema::PARENT_ID})->toBe($parent->id)
        ->and($child->{MenuLinkSchema::MENU_ID})->toBe($menu->id)
        ->and($child->translations()->first()->title)->toBe('New arrivals');
});

it('fails links whose menu reference cannot resolve', function () {
    $this->actingAs($this->superAdminUser());

    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'menu',
        'version' => 1,
        'sections' => [
            ['entity' => 'menus', 'rows' => []],
            ['entity' => 'menu-links', 'rows' => [
                ['source_id' => 1, 'payload' => [
                    'menu_id' => 404, 'parent_id' => null, 'position' => 1,
                    'url' => '/x', 'target' => '_self',
                    'translations' => [['language' => 'en', 'title' => 'X', 'description' => null]],
                ]],
            ]],
        ],
    ];

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('menu.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.errors.0'))->toContain('unresolved_menu')
        ->and(MenuLink::query()->count())->toBe(0);
});

it('skips existing menus with the skip strategy', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferMenu((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedMenuFile();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('menu.json', json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.skipped_count'))->toBe(3)
        ->and(Menu::query()->count())->toBe(1)
        ->and(MenuLink::query()->count())->toBe(2);
});

it('dry run validates without writing', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferMenu((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedMenuFile();

    wipeMenuTables();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('menu.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => true,
    ]);

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(3)
        ->and(Menu::query()->count())->toBe(0)
        ->and(MenuLink::query()->count())->toBe(0);
});
