<?php

namespace Modules\Core\Http\Controllers\Transfer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Models\TransferImport;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Transformers\Transfer\TransferImportResource;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Base for a module's own import-history endpoint
 * (`{module}/admin/data/imports`): the module's runs from the central
 * audit table, latest first.
 */
abstract class ModuleTransferImportsListController extends Controller
{
    abstract protected function moduleName(): string;

    public function __invoke(): AnonymousResourceCollection
    {
        $paginator = QueryBuilder::for(TransferImport::class)
            ->where(TransferImportSchema::MODULE, $this->moduleName())
            ->allowedSorts(TransferImportSchema::ID)
            ->defaultSort('-'.TransferImportSchema::ID)
            ->paginate(Pagination::perPage());

        return TransferImportResource::collection($paginator);
    }
}
