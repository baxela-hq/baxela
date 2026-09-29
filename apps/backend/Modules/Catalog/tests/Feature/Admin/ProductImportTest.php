<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductImport;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\ProductImport\ProductImportSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Tests\Feature\HelperTrait;
use Modules\Core\Models\Language;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function faLanguage(): Language
{
    return Language::query()->firstOrCreate(
        ['code' => 'fa'],
        ['locale' => 'fa', 'name' => 'Persian', 'native_name' => 'فارسی', 'code3' => 'fas', 'is_rtl' => true, 'is_active' => true],
    );
}

function importMedia(string $content, string $extension = 'csv'): Media
{
    Storage::fake('local');
    Storage::disk('local')->put('imports/products.'.$extension, $content);

    return Media::factory()->create([
        MediaSchema::DISK => 'local',
        MediaSchema::PATH => 'imports/products.'.$extension,
        MediaSchema::NAME => 'products',
        MediaSchema::EXTENSION => $extension,
        MediaSchema::MIME_TYPE => 'text/csv',
    ]);
}

function sampleCsv(): string
{
    return "variant.sku,variant.price,variant.quantity,status,is_published,title.fa,slug.fa,title.en,slug.en,categories\n"
        ."BX-TEE-001,450000,25,in_stock,yes,تی‌شرت کلاسیک مردانه,,Men's Classic T-Shirt,,\n";
}

function fullMapping(): array
{
    return [
        'variant.sku' => 'variant.sku',
        'variant.price' => 'variant.price',
        'variant.quantity' => 'variant.quantity',
        'status' => 'status',
        'is_published' => 'is_published',
        'title.fa' => 'title.fa',
        'slug.fa' => 'slug.fa',
        'title.en' => 'title.en',
        'slug.en' => 'slug.en',
        'categories' => null,
    ];
}

function importPayload(Media $media, array $mapping, array $overrides = []): array
{
    return array_merge([
        'media_id' => $media->id,
        'mapping' => $mapping,
        'on_duplicate' => 'update',
        'dry_run' => false,
    ], $overrides);
}

it('previews headers, suggested mapping and sample rows', function () {

    defaultLanguage();
    faLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia(sampleCsv());

    $response = $this->postJson($this->baseUrl('/admin/products/import/preview'), ['media_id' => $media->id]);

    $response->assertOk();
    $mapping = $response->json('data.suggestedMapping');
    expect($response->json('data.headers'))->toBe([
        'variant.sku', 'variant.price', 'variant.quantity', 'status', 'is_published',
        'title.fa', 'slug.fa', 'title.en', 'slug.en', 'categories',
    ])
        ->and($response->json('data.rowCount'))->toBe(1)
        ->and($response->json('data.delimiter'))->toBe(',')
        ->and($response->json('data.sampleRows'))->toHaveCount(1)
        ->and($mapping['variant.sku'])->toBe('variant.sku')
        ->and($mapping['title.en'])->toBe('title.en')
        ->and($mapping['categories'])->toBe('categories')
        ->and($response->json('data.availableFields.variant'))->toContain('variant.barcode')
        ->and($response->json('data.availableFields.fa'))->toContain('title.fa')
        ->and($response->json('data.defaultLanguage'))->toBe('en')
        ->and($response->json('data.warnings'))->toBe([]);
});

it('suggests mapping for shopify-style headers', function () {

    defaultLanguage();
    faLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia("Variant SKU,Variant Price,Variant Barcode,Title (FA),Handle\nA1,10,123,تیشرت,a1\n");

    $response = $this->postJson($this->baseUrl('/admin/products/import/preview'), ['media_id' => $media->id]);

    $response->assertOk();
    $mapping = $response->json('data.suggestedMapping');
    expect($mapping['Variant SKU'])->toBe('variant.sku')
        ->and($mapping['Variant Price'])->toBe('variant.price')
        ->and($mapping['Variant Barcode'])->toBe('variant.barcode')
        ->and($mapping['Title (FA)'])->toBe('title.fa')
        ->and($mapping['Handle'])->toBeNull();
});

it('previews semicolon-delimited files', function () {

    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia("variant.sku;variant.price;title.en\nA1;10;Semicolon Tee\n");

    $response = $this->postJson($this->baseUrl('/admin/products/import/preview'), ['media_id' => $media->id]);

    $response->assertOk();
    expect($response->json('data.delimiter'))->toBe(';')
        ->and($response->json('data.sampleRows.0.0'))->toBe('A1');
});

it('strips the utf-8 bom before parsing', function () {

    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia("\xEF\xBB\xBFvariant.sku,variant.price,title.en\nA1,10,Bom Tee\n");

    $response = $this->postJson($this->baseUrl('/admin/products/import/preview'), ['media_id' => $media->id]);

    $response->assertOk();
    expect($response->json('data.headers.0'))->toBe('variant.sku')
        ->and($response->json('data.suggestedMapping')['variant.sku'])->toBe('variant.sku');
});

it('rejects an empty csv', function () {

    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia("\n");

    $this->postJson($this->baseUrl('/admin/products/import/preview'), ['media_id' => $media->id])
        ->assertStatus(400)
        ->assertJsonPath('code', 'catalog.product.import_failed');
});

it('warns when the row cap is exceeded', function () {

    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $rows = "variant.sku,variant.price,title.en\n";
    for ($i = 0; $i < 1001; $i++) {
        $rows .= "BX-{$i},10,Cap Tee {$i}\n";
    }
    $media = importMedia($rows);

    $response = $this->postJson($this->baseUrl('/admin/products/import/preview'), ['media_id' => $media->id]);

    $response->assertOk();
    expect($response->json('data.rowCount'))->toBe(1001)
        ->and($response->json('data.warnings'))->toContain('row_cap_exceeded');
});

it('imports products with fa and en translations', function () {

    defaultLanguage();
    faLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia(sampleCsv());

    $response = $this->postJson(
        $this->baseUrl('/admin/products/import'),
        importPayload($media, fullMapping()),
    );

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(1)
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.total_rows'))->toBe(1);

    $product = Product::query()->first();
    expect($product)->not->toBeNull()
        ->and($product->{ProductSchema::IS_PUBLISHED})->toBeTrue();

    $translations = $product->translations()->get();
    expect($translations)->toHaveCount(2);

    $fa = $translations->firstWhere('language_id', faLanguage()->id);
    $en = $translations->firstWhere('language_id', defaultLanguage()->id);
    expect($fa->slug)->toBe('تیشرت-کلاسیک-مردانه')
        ->and($en->slug)->toBe('mens-classic-t-shirt');

    $variant = $product->variants()->first();
    expect($variant->{VariantSchema::SKU})->toBe('BX-TEE-001')
        ->and((int) $variant->{VariantSchema::PRICE})->toBe(450000);

    $this->assertDatabaseHas(ProductImportSchema::TABLE, [
        ProductImportSchema::MEDIA_ID => $media->id,
        ProductImportSchema::CREATED_COUNT => 1,
        ProductImportSchema::STATUS => 'completed',
    ]);
});

it('updates existing products when on_duplicate is update', function () {

    defaultLanguage();
    faLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia(sampleCsv());

    $this->postJson($this->baseUrl('/admin/products/import'), importPayload($media, fullMapping()))
        ->assertOk();

    $response = $this->postJson($this->baseUrl('/admin/products/import'), importPayload($media, fullMapping()));

    $response->assertOk();
    expect($response->json('data.updated_count'))->toBe(1)
        ->and($response->json('data.created_count'))->toBe(0)
        ->and(Product::query()->count())->toBe(1)
        ->and(Variant::query()->count())->toBe(1);
});

it('skips existing products when on_duplicate is skip', function () {

    defaultLanguage();
    faLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia(sampleCsv());

    $this->postJson($this->baseUrl('/admin/products/import'), importPayload($media, fullMapping()))
        ->assertOk();

    $response = $this->postJson(
        $this->baseUrl('/admin/products/import'),
        importPayload($media, fullMapping(), ['on_duplicate' => 'skip']),
    );

    $response->assertOk();
    expect($response->json('data.skipped_count'))->toBe(1)
        ->and(Product::query()->count())->toBe(1);
});

it('reports in-file duplicate skus as row errors', function () {

    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $csv = "variant.sku,variant.price,title.en\nBX-DUP,10,First\nBX-DUP,20,Second\n";
    $media = importMedia($csv);

    $mapping = ['variant.sku' => 'variant.sku', 'variant.price' => 'variant.price', 'title.en' => 'title.en'];

    $response = $this->postJson($this->baseUrl('/admin/products/import'), importPayload($media, $mapping));

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(1)
        ->and($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.errors.0.row'))->toBe(3)
        ->and($response->json('data.errors.0.messages.0'))->toContain('Duplicate SKU');
});

it('collects row-numbered errors on partial success', function () {

    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $csv = "variant.sku,variant.price,title.en\nBX-GOOD,10,Good Tee\nBX-BAD,abc,Bad Tee\n";
    $media = importMedia($csv);

    $mapping = ['variant.sku' => 'variant.sku', 'variant.price' => 'variant.price', 'title.en' => 'title.en'];

    $response = $this->postJson($this->baseUrl('/admin/products/import'), importPayload($media, $mapping));

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(1)
        ->and($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.errors.0.row'))->toBe(3);

    $this->assertDatabaseHas(ProductImportSchema::TABLE, [
        ProductImportSchema::FAILED_COUNT => 1,
        ProductImportSchema::STATUS => 'completed',
    ]);
});

it('dry runs without writing products and records history', function () {

    defaultLanguage();
    faLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia(sampleCsv());

    $response = $this->postJson(
        $this->baseUrl('/admin/products/import'),
        importPayload($media, fullMapping(), ['dry_run' => true]),
    );

    $response->assertOk();
    expect($response->json('data.dry_run'))->toBeTrue()
        ->and($response->json('data.created_count'))->toBe(1)
        ->and(Product::query()->count())->toBe(0);

    $this->assertDatabaseHas(ProductImportSchema::TABLE, [
        ProductImportSchema::DRY_RUN => true,
        ProductImportSchema::CREATED_COUNT => 1,
    ]);
});

it('rejects media that is not a csv', function () {

    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia('not really a csv', 'jpg');

    $this->postJson($this->baseUrl('/admin/products/import/preview'), ['media_id' => $media->id])
        ->assertStatus(400)
        ->assertJsonPath('code', 'catalog.product.import_failed');
});

it('generates collision-suffixed slugs', function () {

    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $existing = Product::factory()->create();
    $existing->translations()->create([
        'language_id' => defaultLanguage()->id,
        'title' => 'Cotton Hoodie',
        'slug' => 'cotton-hoodie',
        'content' => '<p>existing</p>',
    ]);

    $csv = "variant.sku,variant.price,title.en\nBX-HOODIE,90,Cotton Hoodie\n";
    $media = importMedia($csv);

    $mapping = ['variant.sku' => 'variant.sku', 'variant.price' => 'variant.price', 'title.en' => 'title.en'];

    $response = $this->postJson($this->baseUrl('/admin/products/import'), importPayload($media, $mapping));

    $response->assertOk();
    $slugs = ProductTranslation::query()
        ->where('slug', 'like', 'cotton-hoodie%')
        ->pluck('slug')
        ->all();
    expect($slugs)->toContain('cotton-hoodie-1');
});

it('downloads the import template with bom and language-aware headers', function () {

    defaultLanguage();
    faLanguage();
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/products/import/template'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');

    $content = $response->getContent();
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($content)->toContain('variant.sku')
        ->and($content)->toContain('variant.barcode')
        ->and($content)->toContain('title.fa')
        ->and($content)->toContain('title.en')
        ->and($content)->toContain('BX-TEE-001');
});

it('lists import history latest first', function () {

    $this->actingAs($this->superAdminUser());

    $older = ProductImport::factory()->create();
    $newer = ProductImport::factory()->create();

    $response = $this->getJson($this->baseUrl('/admin/products/import'));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->first())->toBe($newer->id)
        ->and($ids)->toContain($older->id)
        ->and($response->json('data.0.filename'))->not->toBeEmpty();
});

it('shows a single import run', function () {

    $this->actingAs($this->superAdminUser());

    $import = ProductImport::factory()->create([
        ProductImportSchema::FILENAME => 'products.csv',
    ]);

    $this->getJson($this->baseUrl('/admin/products/import/'.$import->id))
        ->assertOk()
        ->assertJsonPath('data.filename', 'products.csv');
});

it('rejects a mapping missing required fields', function () {

    defaultLanguage();
    faLanguage();
    $this->actingAs($this->superAdminUser());

    $media = importMedia(sampleCsv());

    $this->postJson(
        $this->baseUrl('/admin/products/import'),
        importPayload($media, ['variant.sku' => 'variant.sku', 'title.fa' => 'title.fa']),
    )->assertStatus(422)
        ->assertJsonPath('code', 'http.422');
});

it('denies the import endpoints without permission', function () {

    defaultLanguage();
    $user = User::factory()->create();
    $this->actingAs($user);

    $media = importMedia(sampleCsv());

    $this->postJson($this->baseUrl('/admin/products/import/preview'), ['media_id' => $media->id])
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
    $this->postJson($this->baseUrl('/admin/products/import'), importPayload($media, fullMapping()))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
    $this->getJson($this->baseUrl('/admin/products/import/template'))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
    $this->getJson($this->baseUrl('/admin/products/import'))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
    $this->getJson($this->baseUrl('/admin/products/import/1'))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
});
