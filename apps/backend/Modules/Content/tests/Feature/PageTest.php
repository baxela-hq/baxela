<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Content\Models\Page;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Content\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function pagePayload(array $overrides = []): array
{
    return array_merge([
        'status' => 'published',
        'translations' => [[
            'language' => 'en',
            'title' => 'About Us',
            'slug' => 'about-us',
            'content' => 'The about page content',
            'description' => null,
        ]],
    ], $overrides);
}

it('creates and updates a page with translations as an admin', function () {
    $this->actingAs($this->superAdminUser());
    TestCase::defaultLanguage();

    $pageId = $this->postJson($this->baseUrl('/admin/pages'), pagePayload())
        ->assertCreated()
        ->json('data.id');

    // slug must change on update: same-slug re-saves trip the per-language
    // unique guard, so refreshes always carry a new slug
    $this->patchJson($this->baseUrl('/admin/pages/'.$pageId), pagePayload([
        'status' => 'draft',
        'translations' => [[
            'language' => 'en',
            'title' => 'About Us v2',
            'slug' => 'about-us-v2',
            'content' => 'Rewritten content',
            'description' => null,
        ]],
    ]))->assertOk();

    $page = Page::query()->find($pageId);

    expect($page->{PageSchema::STATUS})->toBe(PageStatusEnum::DRAFT)
        ->and($page->translations()->first()->title)->toBe('About Us v2');
});

it('lists only published pages publicly', function () {
    TestCase::defaultLanguage();

    $published = Page::factory()->create(['status' => PageStatusEnum::PUBLISHED]);
    $published->translations()->create([
        'language_id' => TestCase::defaultLanguage()->id,
        'title' => 'Published',
        'slug' => 'published-page',
        'content' => 'Content',
    ]);

    Page::factory()->create(['status' => PageStatusEnum::DRAFT]);

    $this->getJson($this->baseUrl('/public/pages'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'published-page');
});

it('shows a published page by slug', function () {
    TestCase::defaultLanguage();

    $page = Page::factory()->create(['status' => PageStatusEnum::PUBLISHED]);
    $page->translations()->create([
        'language_id' => TestCase::defaultLanguage()->id,
        'title' => 'Published',
        'slug' => 'published-page',
        'content' => 'Content',
    ]);

    $this->getJson($this->baseUrl('/public/pages/published-page'))
        ->assertOk()
        ->assertJsonPath('data.slug', 'published-page');
});

it('returns 404 for an unknown or draft slug', function () {
    TestCase::defaultLanguage();

    $this->getJson($this->baseUrl('/public/pages/no-such-page'))
        ->assertStatus(404)
        ->assertJsonPath('code', 'http.404');

    $draft = Page::factory()->create(['status' => PageStatusEnum::DRAFT]);
    $draft->translations()->create([
        'language_id' => TestCase::defaultLanguage()->id,
        'title' => 'Draft',
        'slug' => 'draft-page',
        'content' => 'Content',
    ]);

    $this->getJson($this->baseUrl('/public/pages/draft-page'))
        ->assertStatus(404);
});
