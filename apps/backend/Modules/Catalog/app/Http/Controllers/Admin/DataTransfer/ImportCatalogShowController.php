<?php

namespace Modules\Catalog\Http\Controllers\Admin\DataTransfer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Models\CatalogImport;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportEntityEnum;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Catalog\Transformers\Admin\DataTransfer\CatalogImportResource;
use Symfony\Component\HttpFoundation\Response;

class ImportCatalogShowController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $import = CatalogImport::query()
            ->where(CatalogImportSchema::ENTITY, CatalogImportEntityEnum::CATALOG->value)
            ->findOrFail($id);

        return (new CatalogImportResource($import))
            ->response()->setStatusCode(Response::HTTP_OK);
    }
}
