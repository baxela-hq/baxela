<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Models\ProductImport;
use Modules\Catalog\Transformers\Admin\Product\ProductImportResource;
use Symfony\Component\HttpFoundation\Response;

class ImportProductShowController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $import = ProductImport::query()->findOrFail($id);

        return (new ProductImportResource($import))
            ->response()->setStatusCode(Response::HTTP_OK);
    }
}
