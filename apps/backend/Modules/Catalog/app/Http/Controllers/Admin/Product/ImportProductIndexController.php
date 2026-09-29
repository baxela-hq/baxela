<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\Product\ListProductImportAction;
use Modules\Catalog\Transformers\Admin\Product\ProductImportResource;

class ImportProductIndexController extends Controller
{
    public function __construct(protected ListProductImportAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return ProductImportResource::collection($this->action->handle());
    }
}
