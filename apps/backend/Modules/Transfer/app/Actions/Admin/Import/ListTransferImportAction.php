<?php

namespace Modules\Transfer\Actions\Admin\Import;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Core\Models\TransferImport;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Utils\Pagination;
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
