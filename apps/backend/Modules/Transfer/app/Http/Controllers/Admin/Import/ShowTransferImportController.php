<?php

namespace Modules\Transfer\Http\Controllers\Admin\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Transformers\Transfer\TransferImportResource;
use Modules\Transfer\Actions\Admin\Import\ShowTransferImportAction;

class ShowTransferImportController extends Controller
{
    public function __construct(protected ShowTransferImportAction $action) {}

    public function __invoke(int $id): JsonResponse
    {
        return (new TransferImportResource($this->action->handle($id)))
            ->response()->setStatusCode(JsonResponse::HTTP_OK);
    }
}
