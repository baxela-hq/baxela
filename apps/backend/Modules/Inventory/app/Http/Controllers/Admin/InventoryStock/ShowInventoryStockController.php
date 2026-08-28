<?php

namespace Modules\Inventory\Http\Controllers\Admin\InventoryStock;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Actions\Admin\InventoryStock\ShowInventoryStockAction;
use Modules\Inventory\Transformers\Admin\InventoryStock\InventoryStockResource;

class ShowInventoryStockController extends Controller
{
    public function __construct(protected ShowInventoryStockAction $action) {}

    public function __invoke(string $id, Request $request): InventoryStockResource
    {
        return new InventoryStockResource($this->action->handle($id));
    }
}
