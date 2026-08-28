<?php

namespace Modules\Inventory\Http\Controllers\Admin\InventoryStock;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Inventory\Actions\Admin\InventoryStock\CreateInventoryStockAction;
use Modules\Inventory\Http\Requests\Admin\InventoryStock\InventoryStockRequest;
use Modules\Inventory\Transformers\Admin\InventoryStock\InventoryStockResource;

class CreateInventoryStockController extends Controller
{
    public function __construct(protected CreateInventoryStockAction $action) {}

    public function __invoke(InventoryStockRequest $request): JsonResponse
    {
        return InventoryStockResource::make($this->action->handle($request))
            ->response()
            ->setStatusCode(201);
    }
}
