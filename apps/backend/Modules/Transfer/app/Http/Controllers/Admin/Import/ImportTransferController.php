<?php

namespace Modules\Transfer\Http\Controllers\Admin\Import;

use App\Http\Controllers\Controller;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Transfer\Actions\Admin\Import\ImportTransferAction;
use Modules\Transfer\Exceptions\Transfer\ImportFailedException;
use Modules\Transfer\Http\Requests\Admin\TransferImportRequest;
use Modules\Transfer\Schemas\TransferImport\TransferImportSchema;
use Modules\Transfer\Transformers\Admin\TransferImportResultResource;

class ImportTransferController extends Controller
{
    public function __construct(protected ImportTransferAction $action) {}

    /**
     * Replay an uploaded transfer file through the owning module's
     * transfer implementation; dry runs validate without writing.
     *
     * @throws ImportFailedException
     */
    public function __invoke(TransferImportRequest $request): TransferImportResultResource
    {
        $validated = $request->validated();
        $upload = $request->file(TransferImportSchema::REQ_FILE);

        $summary = $this->action->handle(
            basename((string) $upload?->getClientOriginalName()),
            (string) $upload?->getContent(),
            TransferStrategyEnum::from($validated[TransferImportSchema::REQ_ON_DUPLICATE]),
            (bool) $validated[TransferImportSchema::REQ_DRY_RUN],
        );

        return TransferImportResultResource::make($summary);
    }
}
