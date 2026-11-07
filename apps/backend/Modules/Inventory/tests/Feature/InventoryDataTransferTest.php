<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function inventoryTransferTransferFile(string $content): File
{
    return UploadedFile::fake()->createWithContent('transfer.json', $content);
}

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

it('fails rows pointing at variants the local catalog lacks', function () {
    $this->actingAs($this->superAdminUser());

    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'inventory',
        'version' => 1,
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
        ->and($response->json('data.errors.0'))->toContain('unknown_variant:999999')
        ->and(InventoryStock::query()->count())->toBe(0);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
