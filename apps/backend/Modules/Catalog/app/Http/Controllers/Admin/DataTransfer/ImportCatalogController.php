<?php

namespace Modules\Catalog\Http\Controllers\Admin\DataTransfer;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\DataTransfer\ImportCatalogDataAction;
use Modules\Catalog\Exceptions\Data\ImportFailedException;
use Modules\Catalog\Http\Requests\Admin\DataTransfer\ImportCatalogRequest;
use Modules\Catalog\Transformers\Admin\DataTransfer\CatalogImportResultResource;

class ImportCatalogController extends Controller
{
    public function __construct(protected ImportCatalogDataAction $action) {}

    /**
     * @throws ImportFailedException
     */
    public function __invoke(ImportCatalogRequest $request): CatalogImportResultResource
    {
        return CatalogImportResultResource::make($this->action->handle($request->validated()));
    }
}
