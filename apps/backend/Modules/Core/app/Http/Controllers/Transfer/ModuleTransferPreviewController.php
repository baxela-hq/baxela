<?php

namespace Modules\Core\Http\Controllers\Transfer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Http\Requests\Transfer\TransferPreviewRequest;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Transformers\Transfer\TransferImportPreviewResource;

/**
 * Base for a module's own import-preview endpoint
 * (`{module}/admin/data/import/preview`): structure check of an uploaded
 * transfer file before importing.
 */
abstract class ModuleTransferPreviewController extends Controller
{
    abstract protected function moduleName(): string;

    abstract protected function transfer(): ModuleTransferInterface;

    /**
     * @throws ImportFailedException
     */
    public function __invoke(TransferPreviewRequest $request): JsonResponse
    {
        $upload = $request->file(TransferImportSchema::REQ_FILE);

        $preview = app(ModuleTransferRunner::class)->preview(
            basename((string) $upload?->getClientOriginalName()),
            (string) $upload?->getContent(),
            $this->moduleName(),
            $this->transfer(),
        );

        return TransferImportPreviewResource::make($preview)
            ->response()->setStatusCode(JsonResponse::HTTP_OK);
    }
}
