<?php

namespace Modules\Catalog\Http\Controllers\Public\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Actions\Public\Product\ShowProductAction;
use Modules\Catalog\Transformers\Public\Product\ShowProductResource;

class ShowProductController extends Controller
{
    public function __construct(protected ShowProductAction $action) {}

    public function __invoke(string $id, Request $request): JsonResource
    {
        return ShowProductResource::make($this->action->handle($id));
    }
}
