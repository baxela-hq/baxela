<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Models\CatalogImport;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\CategoryTranslation;
use Modules\Catalog\Models\Image;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductAttributeValue;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;
use Modules\Catalog\Schemas\Category\CategoryProductSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Catalog\Support\DataTransfer\CatalogTransferFormat;
use Modules\Catalog\Tests\Feature\HelperTrait;
use Modules\Core\Models\Language;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;

use function Modules\Catalog\Tests\Feature\defaultLanguage;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function transferFaLanguage(): Language
{
    return Language::query()->firstOrCreate(
        ['code' => 'fa'],
        ['locale' => 'fa', 'name' => 'Persian', 'native_name' => 'فارسی', 'code3' => 'fas', 'is_rtl' => true, 'is_active' => true],
    );
}

function transferMedia(string $content): Media
{
    Storage::fake('local');
    Storage::disk('local')->put('imports/catalog.json', $content);

    return Media::factory()->create([
        MediaSchema::DISK => 'local',
        MediaSchema::PATH => 'imports/catalog.json',
        MediaSchema::NAME => 'catalog',
        MediaSchema::EXTENSION => 'json',
        MediaSchema::MIME_TYPE => 'application/json',
    ]);
}

/**
 * Seed a compact but complete catalog: attributes chain, options chain,
 * a category tree and one multi-variant, two-language product.
 */
function seedTransferCatalog(): array
{
    $en = defaultLanguage();
    $fa = transferFaLanguage();

    $group = AttributeGroup::query()->create(['position' => 1]);
    $group->translations()->create(['language_id' => $en->id, 'title' => 'General']);

    $attribute = Attribute::query()->create([
        'group_id' => $group->id, 'code' => 'color', 'data_type' => 'select',
        'is_filterable' => true, 'position' => 1,
    ]);
    $attribute->translations()->create(['language_id' => $en->id, 'title' => 'Color']);

    $attributeValue = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'position' => 1]);
    $attributeValue->translations()->create(['language_id' => $en->id, 'title' => 'Red']);

    $option = Option::query()->create(['position' => 1]);
    $option->translations()->create(['language_id' => $en->id, 'title' => 'Size', 'slug' => 'size']);

    $optionValue = OptionValue::query()->create(['option_id' => $option->id, 'position' => 1]);
    $optionValue->translations()->create(['language_id' => $en->id, 'title' => 'Large', 'slug' => 'large']);

    $parent = Category::query()->create(['parent_id' => null, 'position' => 1, 'is_featured' => true]);
    $parent->translations()->create(['language_id' => $en->id, 'title' => 'Men', 'slug' => 'men']);
    $parent->attributes()->attach([$attribute->id]);

    $child = Category::query()->create(['parent_id' => $parent->id, 'position' => 2]);
    $child->translations()->create(['language_id' => $en->id, 'title' => 'T-Shirts', 'slug' => 't-shirts']);

    $product = Product::query()->create([
        'type' => 'simple', 'status' => 'in_stock', 'is_published' => true, 'is_featured' => false,
    ]);
    $product->translations()->create([
        'language_id' => $en->id, 'title' => 'Classic Tee', 'slug' => 'classic-tee',
        'content' => '<p>content</p>', 'description' => 'short',
    ]);
    $product->translations()->create([
        'language_id' => $fa->id, 'title' => 'تی‌شرت کلاسیک', 'slug' => 'تیشرت-کلاسیک', 'content' => '<p>متن</p>',
    ]);
    $product->categories()->attach([$parent->id, $child->id]);
    $product->variants()->create(['sku' => 'BX-TEE-001', 'price' => '450000', 'quantity' => 5, 'is_default' => true]);
    $product->variants()->create(['sku' => 'BX-TEE-002', 'price' => '460000.5', 'quantity' => 3, 'is_default' => false, 'barcode' => '123']);
    $product->attributeValues()->create(['attribute_id' => $attribute->id, 'attribute_value_id' => $attributeValue->id]);

    return [
        'group' => $group, 'attribute' => $attribute, 'attributeValue' => $attributeValue,
        'option' => $option, 'optionValue' => $optionValue,
        'parent' => $parent, 'child' => $child, 'product' => $product,
    ];
}

function wipeCatalogTables(): void
{
    Variant::query()->delete();
    Image::query()->delete();
    ProductAttributeValue::query()->delete();
    ProductTranslation::query()->delete();
    Product::query()->forceDelete();
    DB::table(CategoryProductSchema::TABLE)->delete();
    DB::table(CategoryAttributeSchema::TABLE)->delete();
    DB::table(VariantOptionValueSchema::TABLE)->delete();
    CategoryTranslation::query()->delete();
    Category::query()->delete();
    OptionValue::query()->delete();
    Option::query()->delete();
    AttributeValue::query()->delete();
    Attribute::query()->delete();
    AttributeGroup::query()->delete();
}

function exportedSections(string $content): array
{
    $file = json_decode($content, true);

    $byEntity = [];
    foreach ($file['sections'] as $section) {
        $byEntity[$section['entity']] = $section['rows'];
    }

    return $byEntity;
}

function transferImportPayload(Media $media, array $overrides = []): array
{
    return array_merge([
        'media_id' => $media->id,
        'on_duplicate' => 'update',
        'dry_run' => false,
    ], $overrides);
}

it('exports the catalog module in dependency order with request-shaped rows', function () {
    seedTransferCatalog();
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/data/export'));

    $response->assertOk();
    $file = json_decode($response->streamedContent(), true);
    expect($file['format'])->toBe('baxela.module-export')
        ->and($file['module'])->toBe('catalog')
        ->and($file['version'])->toBe(1)
        ->and(array_column($file['sections'], 'entity'))->toBe(CatalogTransferFormat::sectionOrder());

    $sections = exportedSections($response->streamedContent());

    // Categories parents-first.
    $categorySlugs = array_map(
        fn ($row) => collect($row['payload']['translations'])->firstWhere('language', 'en')['slug'],
        $sections['categories']
    );
    expect($categorySlugs)->toBe(['men', 't-shirts']);

    // The category references its attributes by source id, in pivot order.
    $menRow = $sections['categories'][0];
    expect($menRow['payload']['attribute_ids'])->toBe([$sections['attributes'][0]['source_id']]);

    // Products carry the full graph: both variants, prices as strings,
    // translations by language code, categories by source id.
    $productRow = $sections['products'][0];
    $payload = $productRow['payload'];
    expect($payload['type'])->toBe('simple')
        ->and($payload['status'])->toBe('in_stock')
        ->and(count($payload['variants']))->toBe(2)
        ->and($payload['variants'][0]['sku'])->toBe('BX-TEE-001')
        ->and($payload['variants'][0]['price'])->toBe('450000')
        ->and($payload['variants'][1]['price'])->toBe('460000.5')
        ->and($payload['variants'][1]['barcode'])->toBe('123')
        ->and(count($payload['translations']))->toBe(2)
        ->and($payload['translations'][0]['language'])->toBe('en')
        ->and($payload['translations'][1]['language'])->toBe('fa')
        ->and(count($payload['categories']))->toBe(2)
        ->and($payload['attribute_values'][0]['attribute_id'])
        ->toBe($sections['attributes'][0]['source_id']);

    // Everything references only sections before it.
    expect($sections['attributes'][0]['payload']['group_id'])
        ->toBe($sections['attribute-groups'][0]['source_id'])
        ->and($sections['attribute-values'][0]['owner'])
        ->toBe($sections['attributes'][0]['source_id'])
        ->and($sections['option-values'][0]['owner'])
        ->toBe($sections['options'][0]['source_id'])
        ->and($sections['categories'][1]['payload']['parent_id'])
        ->toBe($sections['categories'][0]['source_id']);
});

it('downloads the export as a json attachment', function () {
    seedTransferCatalog();
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/data/export'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/json; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment; filename=catalog-')
        ->and($response->headers->get('Content-Disposition'))->toContain('.json');
});

it('round-trips: wipes the catalog and re-imports the export', function () {
    $seeded = seedTransferCatalog();
    $this->actingAs($this->superAdminUser());

    $export = $this->getJson($this->baseUrl('/admin/data/export'))->streamedContent();
    wipeCatalogTables();

    expect(Product::query()->count())->toBe(0);

    $media = transferMedia($export);
    $response = $this->postJson($this->baseUrl('/admin/data/import'), transferImportPayload($media));

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.created_count'))->toBe(8)
        ->and($response->json('data.sections.products.created'))->toBe(1);

    $product = Product::query()->first();
    expect($product)->not->toBeNull()
        ->and($product->variants()->count())->toBe(2)
        ->and($product->variants()->where('sku', 'BX-TEE-002')->exists())->toBeTrue()
        ->and($product->translations()->count())->toBe(2)
        ->and($product->translations()->where('slug', 'تیشرت-کلاسیک')->exists())->toBeTrue()
        ->and($product->categories()->count())->toBe(2);

    // Category tree survived with remapped ids.
    $child = Category::query()->whereHas('translations', fn ($query) => $query->where('slug', 't-shirts'))->first();
    $parent = Category::query()->whereHas('translations', fn ($query) => $query->where('slug', 'men'))->first();
    expect($child->{CategorySchema::PARENT_ID})->toBe($parent->id)
        ->and($child->id)->not->toBe($seeded['child']->id)
        ->and($parent->attributes()->count())->toBe(1)
        ->and($parent->attributes()->first()->code)->toBe('color');

    // Product linked to the remapped categories and attribute value.
    expect($product->categories()->pluck('id')->all())->toContain($parent->id, $child->id)
        ->and($product->attributeValues()->count())->toBe(1)
        ->and($product->attributeValues()->first()->attribute_value_id)->not->toBeNull()
        ->and(Attribute::query()->where('code', 'color')->count())->toBe(1)
        ->and(Option::query()->count())->toBe(1)
        ->and($product->variants()->first()->optionValues()->count())->toBe(0);
});

it('re-imports idempotently with the update strategy', function () {
    seedTransferCatalog();
    $this->actingAs($this->superAdminUser());

    $export = $this->getJson($this->baseUrl('/admin/data/export'))->streamedContent();
    wipeCatalogTables();

    $this->postJson($this->baseUrl('/admin/data/import'), transferImportPayload(transferMedia($export)))
        ->assertOk();

    $response = $this->postJson(
        $this->baseUrl('/admin/data/import'),
        transferImportPayload(transferMedia($export))
    );

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(0)
        ->and($response->json('data.updated_count'))->toBe(8)
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and(Product::query()->count())->toBe(1)
        ->and(Category::query()->count())->toBe(2)
        ->and(Variant::query()->count())->toBe(2);
});

it('skips existing rows with the skip strategy', function () {
    seedTransferCatalog();
    $this->actingAs($this->superAdminUser());

    $export = $this->getJson($this->baseUrl('/admin/data/export'))->streamedContent();
    wipeCatalogTables();

    $this->postJson($this->baseUrl('/admin/data/import'), transferImportPayload(transferMedia($export)))
        ->assertOk();

    $response = $this->postJson(
        $this->baseUrl('/admin/data/import'),
        transferImportPayload(transferMedia($export), ['on_duplicate' => 'skip'])
    );

    $response->assertOk();
    expect($response->json('data.skipped_count'))->toBe(8)
        ->and($response->json('data.created_count'))->toBe(0)
        ->and(Product::query()->count())->toBe(1);
});

it('dry runs without writing anything', function () {
    seedTransferCatalog();
    $this->actingAs($this->superAdminUser());

    $export = $this->getJson($this->baseUrl('/admin/data/export'))->streamedContent();
    wipeCatalogTables();

    $response = $this->postJson(
        $this->baseUrl('/admin/data/import'),
        transferImportPayload(transferMedia($export), ['dry_run' => true])
    );

    $response->assertOk();
    expect($response->json('data.dry_run'))->toBeTrue()
        ->and($response->json('data.created_count'))->toBe(8)
        ->and(Product::query()->count())->toBe(0)
        ->and(Category::query()->count())->toBe(0)
        ->and(AttributeGroup::query()->count())->toBe(0);
});

it('fails product rows referencing unknown category source ids', function () {
    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $file = [
        'format' => 'baxela.module-export', 'module' => 'catalog', 'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'sections' => [
            ['entity' => 'categories', 'rows' => [[
                'source_id' => 1,
                'payload' => [
                    'parent_id' => null, 'position' => null, 'image_media_id' => null,
                    'image_url' => null, 'is_featured' => false, 'attribute_ids' => [],
                    'translations' => [['language' => 'en', 'title' => 'Men', 'slug' => 'men', 'description' => null]],
                ],
            ]]],
            ['entity' => 'products', 'rows' => [[
                'source_id' => 2,
                'payload' => [
                    'type' => 'simple', 'status' => 'in_stock', 'is_published' => true,
                    'categories' => [999],
                    'variants' => [['sku' => 'BX-REF', 'price' => '10', 'quantity' => 1, 'is_default' => true,
                        'barcode' => null, 'compare_price' => null, 'cost_price' => null]],
                    'translations' => [['language' => 'en', 'title' => 'Ref Tee', 'slug' => 'ref-tee',
                        'content' => '<p>x</p>', 'description' => null]],
                ],
            ]]],
        ],
    ];

    $response = $this->postJson(
        $this->baseUrl('/admin/data/import'),
        transferImportPayload(transferMedia(json_encode($file)))
    );

    $response->assertOk();
    expect($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.created_count'))->toBe(1)
        ->and($response->json('data.errors.0.section'))->toBe('products')
        ->and($response->json('data.errors.0.row'))->toBe(1)
        ->and($response->json('data.errors.0.messages.0'))->toContain('Unknown category source id: 999')
        ->and(Product::query()->count())->toBe(0)
        ->and(Category::query()->count())->toBe(1);
});

it('reports unknown language codes as row errors and preview warnings', function () {
    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $file = [
        'format' => 'baxela.module-export', 'module' => 'catalog', 'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'sections' => [
            ['entity' => 'categories', 'rows' => [[
                'source_id' => 1,
                'payload' => [
                    'parent_id' => null, 'position' => null, 'image_media_id' => null,
                    'image_url' => null, 'is_featured' => false, 'attribute_ids' => [],
                    'translations' => [['language' => 'de', 'title' => 'Herren', 'slug' => 'herren', 'description' => null]],
                ],
            ]]],
        ],
    ];
    $media = transferMedia(json_encode($file));

    $preview = $this->postJson($this->baseUrl('/admin/data/import/preview'), ['media_id' => $media->id]);
    $preview->assertOk();
    expect($preview->json('data.warnings'))->toContain('unknown_language:de');

    $run = $this->postJson($this->baseUrl('/admin/data/import'), transferImportPayload($media));
    $run->assertOk();
    expect($run->json('data.failed_count'))->toBe(1)
        ->and($run->json('data.errors.0.messages.0'))->toContain('Unknown language code: de');
});

it('drops images whose media is missing and records a warning', function () {
    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $category = Category::query()->create(['parent_id' => null, 'position' => 1, 'is_featured' => false]);
    $category->translations()->create([
        'language_id' => defaultLanguage()->id, 'title' => 'Featured', 'slug' => 'featured',
    ]);

    $product = Product::query()->create(['type' => 'simple', 'status' => 'in_stock', 'is_published' => true]);
    $product->translations()->create([
        'language_id' => defaultLanguage()->id, 'title' => 'Imaged Tee', 'slug' => 'imaged-tee', 'content' => '<p>x</p>',
    ]);
    $product->categories()->attach([$category->id]);
    $product->variants()->create(['sku' => 'BX-IMG', 'price' => '10', 'quantity' => 1, 'is_default' => true]);
    $product->images()->create(['media_id' => 424242, 'url' => 'https://cdn.test/x.jpg', 'position' => 1, 'collection' => 'photos']);

    $export = $this->getJson($this->baseUrl('/admin/data/export'))->streamedContent();
    wipeCatalogTables();

    $response = $this->postJson(
        $this->baseUrl('/admin/data/import'),
        transferImportPayload(transferMedia($export))
    );

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.warnings'))->toContain('media_missing:424242')
        ->and(Product::query()->count())->toBe(1)
        ->and(Image::query()->count())->toBe(0);
});

it('rejects media that is not json', function () {
    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    Storage::fake('local');
    Storage::disk('local')->put('imports/catalog.csv', 'not json');
    $media = Media::factory()->create([
        MediaSchema::DISK => 'local', MediaSchema::PATH => 'imports/catalog.csv',
        MediaSchema::NAME => 'catalog', MediaSchema::EXTENSION => 'csv', MediaSchema::MIME_TYPE => 'text/csv',
    ]);

    $this->postJson($this->baseUrl('/admin/data/import/preview'), ['media_id' => $media->id])
        ->assertStatus(400)
        ->assertJsonPath('code', 'catalog.data.import_failed');
});

it('rejects a file with an unknown format envelope', function () {
    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    $media = transferMedia(json_encode(['format' => 'something.else', 'module' => 'catalog', 'version' => 1, 'sections' => []]));

    $this->postJson($this->baseUrl('/admin/data/import/preview'), ['media_id' => $media->id])
        ->assertStatus(400)
        ->assertJsonPath('code', 'catalog.data.import_failed');
});

it('previews section counts and totals', function () {
    seedTransferCatalog();
    $this->actingAs($this->superAdminUser());

    $export = $this->getJson($this->baseUrl('/admin/data/export'))->streamedContent();
    $media = transferMedia($export);

    $response = $this->postJson($this->baseUrl('/admin/data/import/preview'), ['media_id' => $media->id]);

    $response->assertOk();
    expect($response->json('data.filename'))->toBe('catalog.json')
        ->and($response->json('data.total_rows'))->toBe(8)
        ->and($response->json('data.row_cap'))->toBe(5000)
        ->and($response->json('data.warnings'))->toBe([])
        ->and(collect($response->json('data.sections'))->where('entity', 'products')->first()['rows'])->toBe(1)
        ->and(collect($response->json('data.sections'))->where('entity', 'categories')->first()['rows'])->toBe(2);
});

it('lists and shows catalog import history scoped to the catalog entity', function () {
    defaultLanguage();
    $this->actingAs($this->superAdminUser());

    CatalogImport::factory()->create([ // product CSV run — must stay invisible here
        'entity' => CatalogImportEntityEnum::PRODUCT->value,
        'filename' => 'products.csv',
    ]);
    $run = CatalogImport::factory()->create([
        'entity' => CatalogImportEntityEnum::CATALOG->value,
        'filename' => 'catalog.json',
    ]);

    $list = $this->getJson($this->baseUrl('/admin/data/import'));
    $list->assertOk();
    $ids = collect($list->json('data'))->pluck('id');
    expect($ids)->toContain($run->id)
        ->and($ids)->not->toContain($ids->count());

    $filenames = collect($list->json('data'))->pluck('filename');
    expect($filenames)->toContain('catalog.json')
        ->and($filenames)->not->toContain('products.csv');

    $this->getJson($this->baseUrl('/admin/data/import/'.$run->id))
        ->assertOk()
        ->assertJsonPath('data.filename', 'catalog.json');
});

it('denies the data transfer endpoints without permission', function () {
    defaultLanguage();
    $user = User::factory()->create();
    $this->actingAs($user);

    $media = transferMedia('{}');

    $this->getJson($this->baseUrl('/admin/data/export'))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
    $this->postJson($this->baseUrl('/admin/data/import/preview'), ['media_id' => $media->id])
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
    $this->postJson($this->baseUrl('/admin/data/import'), transferImportPayload($media))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
    $this->getJson($this->baseUrl('/admin/data/import'))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
    $this->getJson($this->baseUrl('/admin/data/import/1'))
        ->assertStatus(403)->assertJsonPath('code', 'http.403');
});
