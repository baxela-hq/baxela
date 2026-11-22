<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Search\SearchCursorManager;
use Modules\Core\Contracts\Events\Catalog\ProductDeletedEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;

class DeleteProductAction extends AbstractProductAction
{
    public function handle(string $id): bool
    {
        // Explicit transaction (search audit: previously unwrapped) — the
        // journal entry and the deletion commit atomically.
        return DB::transaction(function () use ($id): bool {
            $record = $this->model->query()->findOrFail($id);
            $delete = $record->delete();

            if ($delete) {
                // The product's variants cascade away with it; drop their
                // inventory ledger rows too.
                app(InventoryGatewayInterface::class)->pruneOrphanedStocks();

                // Transactional journal entry: the index writer resolves
                // it into a deletion task (absence is the constraint).
                app(SearchCursorManager::class)->append($record->{ProductSchema::ID});

                event(ProductDeletedEvent::fill($record->toArray()));
            }

            return $delete;
        });
    }
}
