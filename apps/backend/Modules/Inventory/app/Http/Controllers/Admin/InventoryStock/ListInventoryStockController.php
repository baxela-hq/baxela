<?php

namespace Modules\Inventory\Http\Controllers\Admin\InventoryStock;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Inventory\Actions\Admin\InventoryStock\ListInventoryStockAction;
use Modules\Inventory\Transformers\Admin\InventoryStock\InventoryStockResource;

class ListInventoryStockController extends Controller
{
    public function __construct(protected ListInventoryStockAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return InventoryStockResource::collection($this->action->handle());
    }
}
