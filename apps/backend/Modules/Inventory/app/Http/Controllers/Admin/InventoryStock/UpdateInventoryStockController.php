<?php

namespace Modules\Inventory\Http\Controllers\Admin\InventoryStock;

use App\Http\Controllers\Controller;
use Modules\Inventory\Actions\Admin\InventoryStock\UpdateInventoryStockAction;
use Modules\Inventory\Http\Requests\Admin\InventoryStock\InventoryStockRequest;
use Modules\Inventory\Transformers\Admin\InventoryStock\InventoryStockResource;

class UpdateInventoryStockController extends Controller
{
    public function __construct(protected UpdateInventoryStockAction $action) {}

    public function __invoke(string $id, InventoryStockRequest $request): InventoryStockResource
    {
        return new InventoryStockResource($this->action->handle($id, $request));
    }
}
