<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Product\ImportProductsAction;
use Modules\Catalog\Exceptions\Product\ImportFailedException;
use Modules\Catalog\Http\Requests\Admin\Product\ImportProductsRequest;
use Modules\Catalog\Transformers\Admin\Product\ProductImportResultResource;

class ImportProductsController extends Controller
{
    public function __construct(protected ImportProductsAction $action) {}

    /**
     * @throws ImportFailedException
     */
    public function __invoke(ImportProductsRequest $request): ProductImportResultResource
    {
        return ProductImportResultResource::make($this->action->handle($request->validated()));
    }
}
