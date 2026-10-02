<?php

namespace Modules\Catalog\Http\Controllers\Admin\DataTransfer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\DataTransfer\ListCatalogImportAction;
use Modules\Catalog\Transformers\Admin\DataTransfer\CatalogImportResource;

class ImportCatalogIndexController extends Controller
{
    public function __construct(protected ListCatalogImportAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return CatalogImportResource::collection($this->action->handle());
    }
}
