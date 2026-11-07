<?php

namespace Modules\Core\Http\Controllers\Transfer;

use App\Http\Controllers\Controller;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Http\Requests\Transfer\TransferImportRequest;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Transformers\Transfer\TransferImportResultResource;

/**
 * Base for a module's own import endpoint (`{module}/admin/data/import`):
 * replays an uploaded transfer file through the module's transfer
 * implementation; dry runs validate without writing. Runs persist to the
 * central audit table.
 */
abstract class ModuleTransferImportController extends Controller
{
    abstract protected function moduleName(): string;

    abstract protected function transfer(): ModuleTransferInterface;

    /**
     * @throws ImportFailedException
     */
    public function __invoke(TransferImportRequest $request): TransferImportResultResource
    {
        $validated = $request->validated();
        $upload = $request->file(TransferImportSchema::REQ_FILE);

        $summary = app(ModuleTransferRunner::class)->run(
            basename((string) $upload?->getClientOriginalName()),
            (string) $upload?->getContent(),
            TransferStrategyEnum::from($validated[TransferImportSchema::REQ_ON_DUPLICATE]),
            (bool) $validated[TransferImportSchema::REQ_DRY_RUN],
            $this->moduleName(),
            $this->transfer(),
        );

        return TransferImportResultResource::make($summary);
    }
}
