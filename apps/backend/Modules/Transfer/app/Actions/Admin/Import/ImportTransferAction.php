<?php

namespace Modules\Transfer\Actions\Admin\Import;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Utils\Auth;
use Modules\Transfer\Exceptions\Transfer\ImportFailedException;
use Modules\Transfer\Models\TransferImport;
use Modules\Transfer\Schemas\TransferImport\TransferImportSchema;
use Modules\Transfer\Schemas\TransferImport\TransferImportStatusEnum;
use Modules\Transfer\Support\TransferFile;
use Modules\Transfer\Support\TransferRegistry;
use RuntimeException;
use Throwable;

/**
 * Replay a module transfer file: sections in the module's declared
 * dependency order, each handed to the owning module's transfer
 * implementation, which remaps source ids, resolves language codes and
 * writes through its own admin Actions. Per-row failures are recorded,
 * not thrown; the run (dry runs included) is persisted as an auditable
 * TransferImport row.
 */
class ImportTransferAction
{
    public function __construct(protected TransferRegistry $registry) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function handle(string $filename, string $content, TransferStrategyEnum $strategy, bool $dryRun): array
    {
        $startedAt = microtime(true);

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

        $totalRows = TransferFile::totalRows($file);
        if ($totalRows > (int) config('transfer.row_cap')) {
            throw new ImportFailedException(meta: ['reason' => 'row_cap_exceeded']);
        }

        $options = new ImportOptions($strategy, $dryRun);
        $declared = [];
        foreach ($transfer->sections() as $section) {
            $declared[$section->key] = $section;
        }

        $sectionSummary = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $warnings = [];

        foreach ($sections as $sectionKey => $rows) {
            $rows = array_values($rows);

            if (! $declared[$sectionKey]->importable) {
                $skipped += count($rows);
                $warnings[] = 'not_importable:'.$sectionKey;
                $sectionSummary[$sectionKey] = ['created' => 0, 'updated' => 0, 'skipped' => count($rows), 'failed' => 0];

                continue;
            }

            $result = $transfer->importRows($sectionKey, $rows, $options);
            $counts = $result->counts();
            $sectionSummary[$sectionKey] = $counts;

            $created += $counts['created'];
            $updated += $counts['updated'];
            $skipped += $counts['skipped'];
            $failed += $counts['failed'];
            $errors = array_merge($errors, $result->errors);
            $warnings = array_merge($warnings, $result->warnings);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $errorCap = (int) config('transfer.error_cap');
        $errors = array_slice($errors, 0, $errorCap);
        $warnings = array_values(array_unique($warnings));
        $status = ($failed > 0 && $created + $updated + $skipped === 0)
            ? TransferImportStatusEnum::FAILED
            : TransferImportStatusEnum::COMPLETED;

        $record = $this->persist(
            $module,
            $filename,
            $status,
            $strategy,
            $dryRun,
            $totalRows,
            $created,
            $updated,
            $skipped,
            $failed,
            $durationMs,
            $errors,
            $sectionSummary,
            $warnings,
        );

        return [
            'id' => (int) $record->getKey(),
            'module' => $module,
            'status' => $status->value,
            'strategy' => $strategy->value,
            'dry_run' => $dryRun,
            'total_rows' => $totalRows,
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
            'duration_ms' => $durationMs,
            'sections' => $sectionSummary,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * Replay transaction boundaries belong to the module implementations
     * (per-section or per-row), so a partial run's writes stay committed;
     * only this audit row is wrapped in its own transaction.
     *
     * @param  array<string, array<string, int>>  $sectionSummary
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     *
     * @throws Throwable
     */
    protected function persist(
        string $module,
        string $filename,
        TransferImportStatusEnum $status,
        TransferStrategyEnum $strategy,
        bool $dryRun,
        int $totalRows,
        int $created,
        int $updated,
        int $skipped,
        int $failed,
        int $durationMs,
        array $errors,
        array $sectionSummary,
        array $warnings,
    ): TransferImport {
        DB::beginTransaction();

        try {
            $record = TransferImport::query()->create([
                TransferImportSchema::USER_ID => Auth::id(),
                TransferImportSchema::MODULE => $module,
                TransferImportSchema::FILENAME => $filename,
                TransferImportSchema::STATUS => $status->value,
                TransferImportSchema::STRATEGY => $strategy->value,
                TransferImportSchema::DRY_RUN => $dryRun,
                TransferImportSchema::TOTAL_ROWS => $totalRows,
                TransferImportSchema::CREATED_COUNT => $created,
                TransferImportSchema::UPDATED_COUNT => $updated,
                TransferImportSchema::SKIPPED_COUNT => $skipped,
                TransferImportSchema::FAILED_COUNT => $failed,
                TransferImportSchema::DURATION_MS => $durationMs,
                TransferImportSchema::ERRORS => $errors,
                TransferImportSchema::SUMMARY => [
                    'sections' => $sectionSummary,
                    'warnings' => $warnings,
                ],
            ]);

            DB::commit();

            return $record;
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);

            throw $e;
        }
    }
}
