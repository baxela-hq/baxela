<?php

namespace Modules\Transfer\Http\Controllers\Admin\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Requests\Transfer\TransferPreviewRequest;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Transformers\Transfer\TransferImportPreviewResource;
use Modules\Transfer\Actions\Admin\Import\PreviewTransferImportAction;
use Modules\Transfer\Exceptions\Transfer\ImportFailedException;

class PreviewTransferImportController extends Controller
{
    public function __construct(protected PreviewTransferImportAction $action) {}

    /**
     * Structure check of an uploaded transfer file, before importing.
     *
     * @throws ImportFailedException
     */
    public function __invoke(TransferPreviewRequest $request): JsonResponse
    {
        $upload = $request->file(TransferImportSchema::REQ_FILE);
        $filename = basename((string) $upload?->getClientOriginalName());

        return TransferImportPreviewResource::make(
            $this->action->handle($filename, (string) $upload?->getContent()),
        )->response()->setStatusCode(JsonResponse::HTTP_OK);
    }
}
