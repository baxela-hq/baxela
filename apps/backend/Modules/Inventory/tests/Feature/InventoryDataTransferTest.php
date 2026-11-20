<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Core\Models\TransferRemap;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function inventoryTransferTransferFile(string $content): File
{
    return UploadedFile::fake()->createWithContent('transfer.json', $content);
}

/** @return array{product: Product, variant: Variant, stock: InventoryStock} */
function inventoryTransferStock(int $quantity): array
{
    $product = Product::query()->create(['type' => 'simple', 'status' => 'in_stock', 'is_published' => true]);
    $variant = Variant::factory()->create(['product_id' => $product->id]);
    $stock = InventoryStock::factory()->ofVariant($variant, $quantity)->create();

    return ['product' => $product, 'variant' => $variant, 'stock' => $stock];
}

it('exports the stock ledger through the module endpoint', function () {
    $seeded = inventoryTransferStock(12);
    $this->actingAs($this->superAdminUser());

    $file = json_decode(
        $this->getJson($this->baseUrl('/admin/data/export'))->assertOk()->streamedContent(),
        true
    );

    expect($file['module'])->toBe('inventory')
        ->and(array_column($file['sections'], 'entity'))->toBe(['stocks'])
        ->and($file['sections'][0]['rows'][0]['payload']['variant_id'])->toBe($seeded['variant']->id)
        ->and($file['sections'][0]['rows'][0]['payload']['quantity'])->toBe(12);
});

it('round-trips the stock ledger via the module endpoint', function () {
    $seeded = inventoryTransferStock(12);
    $this->actingAs($this->superAdminUser());

    $file = json_decode(
        $this->getJson($this->baseUrl('/admin/data/export'))->assertOk()->streamedContent(),
        true
    );

    InventoryStock::query()->delete();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => inventoryTransferTransferFile(json_encode($file)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(1)
        ->and(InventoryStock::query()->count())->toBe(1)
        // The stock re-attached to the surviving variant at the exact quantity.
        ->and(InventoryStock::query()->where('variant_id', $seeded['variant']->id)->value('quantity'))->toBe(12);
});

it('resolves the variant through a persisted remap before raw ids (C2 tier 1)', function () {
    // A variant with NO stock row yet, so the import creates one.
    $product = Product::query()->create(['type' => 'simple', 'status' => 'in_stock', 'is_published' => true]);
    $variant = Variant::factory()->create(['product_id' => $product->id]);
    $this->actingAs($this->superAdminUser());

    // A previous real import of source X recorded: X's variant 500 is
    // THIS store's variant — which here has a completely different id.
    $source = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    TransferRemap::query()->create([
        'source_installation' => $source,
        'module' => 'catalog',
        'entity' => 'variants',
        'source_id' => 500,
        'local_id' => $variant->id,
    ]);

    // X's file carries variant 500 — an id that exists nowhere locally.
    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'inventory',
        'version' => 1,
        'source_installation' => $source,
        'sections' => [
            ['entity' => 'stocks', 'rows' => [
                ['source_id' => 1, 'payload' => ['variant_id' => 500, 'quantity' => 4]],
            ]],
        ],
    ];

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => inventoryTransferTransferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(1)
        // The stock landed on the remapped LOCAL variant, not id 500.
        ->and(InventoryStock::query()->where('variant_id', $variant->id)->value('quantity'))->toBe(4)
        ->and(InventoryStock::query()->where('variant_id', 500)->count())->toBe(0);
});

it('fails rows pointing at variants the local catalog lacks', function () {
    $this->actingAs($this->superAdminUser());

    // An IDENTIFIED file (exported stores carry their identity): the raw
    // tier is automatic, so the failure is the plain unknown-variant one.
    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'inventory',
        'version' => 1,
        'source_installation' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'sections' => [
            ['entity' => 'stocks', 'rows' => [
                ['source_id' => 1, 'payload' => ['variant_id' => 999999, 'quantity' => 5]],
            ]],
        ],
    ];

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => inventoryTransferTransferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.errors.0'))->toContain('unknown_variants:999999')
        ->and(InventoryStock::query()->count())->toBe(0);
});

it('fails unresolved variant refs on legacy files unless raw ids are allowed (C2/F16)', function () {
    $this->actingAs($this->superAdminUser());

    $buildFile = fn (bool $allowRaw) => $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => inventoryTransferTransferFile(json_encode([
            'format' => 'baxela.module-export',
            'module' => 'inventory',
            'version' => 1,
            'sections' => [
                ['entity' => 'stocks', 'rows' => [
                    ['source_id' => 1, 'payload' => ['variant_id' => 999999, 'quantity' => 5]],
                ]],
            ],
        ])),
        'on_duplicate' => 'update',
        'dry_run' => false,
        'allow_raw_ids' => $allowRaw,
    ])->assertOk();

    // Legacy default: fail loudly instead of silently trusting a raw id.
    $legacy = $buildFile(false);
    expect($legacy->json('data.failed_count'))->toBe(1)
        ->and($legacy->json('data.errors.0'))->toContain('ref_unresolvable_legacy:catalog.variants:999999')
        ->and(InventoryStock::query()->count())->toBe(0);

    // Explicit compatibility mode: the raw tier is allowed (still fails —
    // the variant genuinely does not exist — but with the plain reason).
    $allowed = $buildFile(true);
    expect($allowed->json('data.failed_count'))->toBe(1)
        ->and($allowed->json('data.errors.0'))->toContain('unknown_variants:999999');
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
