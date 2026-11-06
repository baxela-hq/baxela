<?php

namespace Modules\Transfer\Actions\Admin\Export;

use Modules\Transfer\Exceptions\Transfer\ExportFailedException;
use Modules\Transfer\Exceptions\Transfer\ModuleNotSupportedException;
use Modules\Transfer\Support\TransferFormat;
use Modules\Transfer\Support\TransferRegistry;

/**
 * Build a module's transfer file: sections in the module's declared
 * dependency order, each filled with request-shaped rows. The export
 * itself carries source ids and language codes only — portability comes
 * from the importer's remapping.
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

        $sections = [];
        $totalRows = 0;
        foreach ($transfer->sections() as $section) {
            if (! $section->exportable) {
                continue;
            }

            $rows = $transfer->exportRows($section->key);
            $totalRows += count($rows);
            $sections[] = [
                TransferFormat::KEY_ENTITY => $section->key,
                TransferFormat::KEY_ROWS => $rows,
            ];
        }

        if ($totalRows > (int) config('transfer.row_cap')) {
            throw new ExportFailedException(meta: ['reason' => 'row_cap_exceeded']);
        }

        return [
            TransferFormat::KEY_FORMAT => TransferFormat::FORMAT,
            TransferFormat::KEY_MODULE => $module,
            TransferFormat::KEY_VERSION => TransferFormat::VERSION,
            TransferFormat::KEY_EXPORTED_AT => now()->toIso8601String(),
            TransferFormat::KEY_SECTIONS => $sections,
        ];
    }
}
