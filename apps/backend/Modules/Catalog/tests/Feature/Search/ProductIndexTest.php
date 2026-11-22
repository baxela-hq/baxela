<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Search\NullSearchClient;
use Modules\Catalog\Search\ProductDocumentBuilder;
use Modules\Catalog\Search\ProductIndexWriter;
use Modules\Catalog\Search\ProductSearchQuery;
use Modules\Catalog\Search\ProductSearchResult;
use Modules\Catalog\Search\SearchClientInterface;
use Modules\Catalog\Search\SearchGenerationGuard;
use Modules\Catalog\Search\SearchOutbox;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;

uses(RefreshDatabase::class);

/**
 * Fake engine boundary: records submissions/deletions per uid without
 * speaking to a real Meilisearch (the real-adapter tests run in the e2e
 * workflow against the compose `search` profile).
 */
final class RecordingSearchClient implements SearchClientInterface
{
    /** @var list<array<string, mixed>> */
    public array $documents = [];

    /** @var list<int> */
    public array $deletions = [];

    public bool $success = true;

    public function configured(): bool
    {
        return true;
    }

    public function search(ProductSearchQuery $query): ProductSearchResult
    {
        return new ProductSearchResult([], 0, 0);
    }

    public function submitDocuments(string $indexUid, array $documents): string
    {
        $this->documents[$indexUid] = array_merge($this->documents[$indexUid] ?? [], $documents);

        return 'task-'.count($this->documents[$indexUid]);
    }

    public function submitDeletion(string $indexUid, int $productId): string
    {
        $this->deletions[$indexUid][] = $productId;

        return 'task-del-'.count($this->deletions[$indexUid]);
    }

    /** @var array<string, array<int, array<string, mixed>>> documents by primary key */
    public array $documentsById = [];

    public function getDocument(string $indexUid, int $productId): ?array
    {
        foreach ($this->documents[$indexUid] ?? [] as $document) {
            if ((int) ($document['product_id'] ?? 0) === $productId) {
                return $document;
            }
        }

        return null;
    }

    public function ensureIndex(string $indexUid): void {}

    public function deleteIndex(string $indexUid): void {}

    public function taskStatus(string $taskUid): ?string
    {
        return $this->success ? 'processed' : 'failed';
    }

    public function awaitTask(string $taskUid): bool
    {
        return $this->success;
    }
}

function seedIndexCatalog(): Product
{
    Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    Language::query()->firstOrCreate(['code' => 'en'], ['locale' => 'en', 'name' => 'English', 'code3' => 'eng', 'is_active' => true]);

    $category = Category::query()->create(['position' => 1]);
    $category->translations()->create(['language_id' => 1, 'title' => 'Men', 'slug' => 'men']);

    $option = Option::query()->create(['position' => 1]);
    $optionValue = OptionValue::query()->create(['option_id' => $option->id, 'position' => 1]);

    $product = Product::query()->create([
        'type' => 'variable',
        'status' => ProductStatusEnum::IN_STOCK,
        'is_published' => true,
        'base_price' => '25.00',
        'rating_avg' => '4.20',
        'rating_count' => 3,
    ]);
    $product->categories()->attach([$category->id]);
    $product->translations()->create(['language_id' => 1, 'title' => 'Test Shirt', 'slug' => 'test-shirt', 'content' => 'Content']);

    $variant = $product->variants()->create(['sku' => 'SKU-1', 'price' => '25.00', 'quantity' => 5, 'is_default' => true]);
    $variant->optionValues()->attach([$optionValue->id]);

    return $product;
}

it('builds a product-level document with locale titles, price bounds and the option union', function () {
    $product = seedIndexCatalog();

    // A second, pricier variant with the same option — the union stays
    // per-option, the bounds span both variants.
    $product->variants()->create(['sku' => 'SKU-2', 'price' => '40.00', 'quantity' => 3]);

    $doc = app(ProductDocumentBuilder::class)->build($product->fresh());

    expect($doc['product_id'])->toBe($product->id)
        ->and($doc['title_en'])->toBe('Test Shirt')
        ->and($doc['price_min'])->toBe(2500)
        ->and($doc['price_max'])->toBe(4000)
        ->and($doc['option_value_ids'])->toBe([1])
        ->and($doc['rating_avg'])->toBe(4.2)
        ->and($doc['category_ids'])->toBe([$category->id ?? 1]);
});

it('indexes the journal entry and releases the uniqueness key on terminal success', function () {
    $product = seedIndexCatalog();
    $outbox = app(SearchOutbox::class);
    $entryId = $outbox->record($product->id);

    $writer = new ProductIndexWriter(new RecordingSearchClient, app(SearchGenerationGuard::class), app(ProductDocumentBuilder::class));
    expect($writer->write($product->id))->toBeTrue();

    $row = DB::table('catalog_search_operations')->where('id', $entryId)->first();
    expect($row->task_status)->toBe('processed')
        ->and($row->resolved_at)->not->toBeNull()
        ->and($row->doc_key)->toBeNull();
});

it('supersedes a previous unresolved entry when a mutation journals again', function () {
    $product = seedIndexCatalog();
    $outbox = app(SearchOutbox::class);
    $firstId = $outbox->record($product->id);
    $secondId = $outbox->record($product->id);

    $first = DB::table('catalog_search_operations')->where('id', $firstId)->first();
    $second = DB::table('catalog_search_operations')->where('id', $secondId)->first();

    expect($first->superseded_at)->not->toBeNull()
        ->and($first->doc_key)->toBeNull()
        ->and($second->superseded_at)->toBeNull()
        ->and($second->doc_key)->toBe((string) $product->id);
});

it('submits a deletion when the product becomes ineligible', function () {
    $recording = new RecordingSearchClient;
    $product = seedIndexCatalog();
    app(SearchOutbox::class)->record($product->id);

    // The product goes out of stock after the journal entry was written.
    $product->update(['status' => ProductStatusEnum::OUT_OF_STOCK]);

    $writer = new ProductIndexWriter($recording, app(SearchGenerationGuard::class), app(ProductDocumentBuilder::class));
    expect($writer->write($product->id))->toBeTrue();

    expect($recording->documents)->toBeEmpty()
        ->and($recording->deletions)->not->toBeEmpty();
});

it('skips the writer when the engine is not configured', function () {
    // Unconfigured client: the listener short-circuit path — the journal
    // entry stays durable/unresolved for the nightly reindex.
    $product = seedIndexCatalog();
    app(SearchOutbox::class)->record($product->id);

    $writer = new ProductIndexWriter(new NullSearchClient, app(SearchGenerationGuard::class), app(ProductDocumentBuilder::class));
    expect($writer->configured())->toBeFalse()
        ->and($writer->write($product->id))->toBeFalse();
});
