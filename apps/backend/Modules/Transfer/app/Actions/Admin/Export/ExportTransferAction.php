<?php

namespace Modules\Transfer\Actions\Admin\Export;

use Modules\Core\Exceptions\Transfer\ExportFailedException;
use Modules\Core\Exceptions\Transfer\ModuleNotSupportedException;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Transfer\Support\TransferRegistry;

/**
 * Build a module's transfer file from the registry-resolved
 * implementation. The file plane itself lives in the Core runner, shared
 * with the per-module data endpoints.
 */
class ExportTransferAction
{
    public function __construct(protected TransferRegistry $registry) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ModuleNotSupportedException
     * @throws ExportFailedException
     */
    public function handle(string $module): array
    {
        $transfer = $this->registry->forModule($module);

        if ($transfer === null) {
            throw new ModuleNotSupportedException(meta: ['module' => $module]);
        }

        return app(ModuleTransferRunner::class)->envelope($transfer, $module);
    }
}
