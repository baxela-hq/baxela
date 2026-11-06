<?php

namespace Modules\Transfer\Actions\Admin\Import;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Core\Utils\Pagination;
use Modules\Transfer\Models\TransferImport;
use Modules\Transfer\Schemas\TransferImport\TransferImportSchema;
use Spatie\QueryBuilder\QueryBuilder;

class ListTransferImportAction
{
    /**
     * Transfer import history, latest first.
     *
     * @return LengthAwarePaginator<int, TransferImport>
     */
    public function handle(): LengthAwarePaginator
    {
        return QueryBuilder::for(TransferImport::class)
            ->allowedSorts(TransferImportSchema::ID)
            ->defaultSort('-'.TransferImportSchema::ID)
            ->paginate(Pagination::perPage());
    }
}
