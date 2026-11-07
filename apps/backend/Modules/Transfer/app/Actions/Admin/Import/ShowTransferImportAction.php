<?php

namespace Modules\Transfer\Actions\Admin\Import;

use Modules\Core\Models\TransferImport;

class ShowTransferImportAction
{
    public function handle(int $id): TransferImport
    {
        return TransferImport::query()->findOrFail($id);
    }
}
