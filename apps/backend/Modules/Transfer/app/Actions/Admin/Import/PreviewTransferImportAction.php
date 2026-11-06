<?php

namespace Modules\Transfer\Actions\Admin\Import;

use Modules\Transfer\Exceptions\Transfer\ImportFailedException;
use Modules\Transfer\Support\TransferFile;
use Modules\Transfer\Support\TransferFormat;
use Modules\Transfer\Support\TransferRegistry;
use RuntimeException;

/**
 * Structure check of an uploaded transfer file: envelope, the owning
 * module's declared sections, per-section row counts, the row cap and
 * which sections would be skipped because they are export-only.
 */
class PreviewTransferImportAction
{
    public function __construct(protected TransferRegistry $registry) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function handle(string $filename, string $content): array
    {
        try {
            $file = TransferFile::decode($content);
        } catch (RuntimeException $e) {
            throw new ImportFailedException(meta: ['reason' => $e->getMessage()]);
        }

        $module = TransferFile::module($file);
        $transfer = $this->registry->forModule($module);

        if ($transfer === null) {
            throw new ImportFailedException(meta: ['reason' => 'unknown_module:'.$module]);
        }

        try {
            $sections = TransferFile::orderedSections($file, $transfer);
        } catch (RuntimeException $e) {
            throw new ImportFailedException(meta: ['reason' => $e->getMessage()]);
        }

        $rowCap = (int) config('transfer.row_cap');
        $declared = [];
        foreach ($transfer->sections() as $section) {
            $declared[$section->key] = $section;
        }

        $sectionRows = [];
        $totalRows = 0;
        $warnings = [];
        foreach ($transfer->sections() as $section) {
            if (! isset($sections[$section->key])) {
                continue;
            }

            $count = count($sections[$section->key]);
            $totalRows += $count;
            $sectionRows[] = [
                TransferFormat::KEY_ENTITY => $section->key,
                'rows' => $count,
            ];

            if (! $section->importable) {
                $warnings[] = 'not_importable:'.$section->key;
            }
        }

        if ($totalRows > $rowCap) {
            $warnings[] = 'row_cap_exceeded';
        }

        return [
            'filename' => $filename,
            'format' => $file[TransferFormat::KEY_FORMAT],
            'module' => $module,
            'version' => (int) $file[TransferFormat::KEY_VERSION],
            'exported_at' => $file[TransferFormat::KEY_EXPORTED_AT] ?? null,
            'sections' => $sectionRows,
            'total_rows' => $totalRows,
            'row_cap' => $rowCap,
            'warnings' => $warnings,
        ];
    }
}
