<?php

namespace Modules\Transfer\Http\Controllers\Admin\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Transfer\Actions\Admin\Import\ListTransferImportAction;
use Modules\Transfer\Transformers\Admin\TransferImportResource;

class ListTransferImportController extends Controller
{
    public function __construct(protected ListTransferImportAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return TransferImportResource::collection($this->action->handle());
    }
}
