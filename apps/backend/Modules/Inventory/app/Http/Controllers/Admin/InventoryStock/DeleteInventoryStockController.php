<?php

namespace Modules\Inventory\Http\Controllers\Admin\InventoryStock;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Actions\Admin\InventoryStock\DeleteInventoryStockAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteInventoryStockController extends Controller
{
    public function __construct(protected DeleteInventoryStockAction $action) {}

    public function __invoke(string $id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noInventory(Response::HTTP_NO_CONTENT);
    }
}
