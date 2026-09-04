<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Modules\Core\Contracts\Events\Catalog\ProductDeletedEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;

class DeleteProductAction extends AbstractProductAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->query()->findOrFail($id);
        $delete = $record->delete();

        if ($delete) {
            // The product's variants cascade away with it; drop their
            // inventory ledger rows too.
            app(InventoryGatewayInterface::class)->pruneOrphanedStocks();

            event(ProductDeletedEvent::fill($record->toArray()));
        }

        return $delete;
    }
}
