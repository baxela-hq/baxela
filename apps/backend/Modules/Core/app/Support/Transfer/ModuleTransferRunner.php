<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Models\TransferImport;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Schemas\TransferImport\TransferImportStatusEnum;
use Modules\Core\Utils\Auth;
use RuntimeException;
use Throwable;

/**
 * The transfer file plane, shared by the central Transfer module and the
 * per-module data endpoints: builds a module's envelope, structure-checks
 * an uploaded file, and replays it — persisting every run (dry runs
 * included) to the central audit table.
 *
 * The caller supplies the resolved `ModuleTransferInterface` and the
 * module name it belongs to; the runner never resolves implementations
 * itself, so it works identically for registry-driven central endpoints
 * and self-contained per-module ones.
 */
final class ModuleTransferRunner
{
    /**
     * Build a module's transfer envelope: sections in the module's
     * declared dependency order, each filled with request-shaped rows.
     *
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function envelope(ModuleTransferInterface $transfer, string $module): array
    {
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

        if ($totalRows > (int) config('core.transfer.row_cap')) {
            throw new ImportFailedException(meta: ['reason' => 'row_cap_exceeded']);
        }

        return [
            TransferFormat::KEY_FORMAT => TransferFormat::FORMAT,
            TransferFormat::KEY_MODULE => $module,
            TransferFormat::KEY_VERSION => TransferFormat::VERSION,
            TransferFormat::KEY_EXPORTED_AT => now()->toIso8601String(),
            TransferFormat::KEY_SECTIONS => $sections,
        ];
    }

    /**
     * Structure check of an uploaded transfer file: envelope, the owning
     * module's declared sections, per-section row counts, the row cap
     * and which sections would be skipped because they are export-only.
     * Rejects files that declare a different module than the endpoint's.
     *
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function preview(string $filename, string $content, string $module, ModuleTransferInterface $transfer): array
    {
        $file = $this->decode($content, $module);

        try {
            $sections = TransferFile::orderedSections($file, $transfer);
        } catch (RuntimeException $e) {
            throw new ImportFailedException(meta: ['reason' => $e->getMessage()]);
        }

        $rowCap = (int) config('core.transfer.row_cap');
        $importableBySection = [];
        foreach ($transfer->sections() as $section) {
            $importableBySection[$section->key] = $section->importable;
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

    /**
     * Replay a module transfer file: sections in the module's declared
     * dependency order, each handed to the owning module's transfer
     * implementation. Per-row failures are recorded, not thrown; the run
     * (dry runs included) is persisted as an auditable TransferImport.
     *
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function run(
        string $filename,
        string $content,
        TransferStrategyEnum $strategy,
        bool $dryRun,
        string $module,
        ModuleTransferInterface $transfer,
    ): array {
        $startedAt = microtime(true);

        $file = $this->decode($content, $module);

        try {
            $sections = TransferFile::orderedSections($file, $transfer);
        } catch (RuntimeException $e) {
            throw new ImportFailedException(meta: ['reason' => $e->getMessage()]);
        }

        $totalRows = TransferFile::totalRows($file);
        if ($totalRows > (int) config('core.transfer.row_cap')) {
            throw new ImportFailedException(meta: ['reason' => 'row_cap_exceeded']);
        }

        $options = new ImportOptions($strategy, $dryRun);
        $importableBySection = [];
        foreach ($transfer->sections() as $section) {
            $importableBySection[$section->key] = $section->importable;
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

            if (! $importableBySection[$sectionKey]) {
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
        $errorCap = (int) config('core.transfer.error_cap');
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
     * Decode + validate the envelope and enforce that the file declares
     * the module it is being served to.
     *
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    private function decode(string $content, string $expectedModule): array
    {
        try {
            $file = TransferFile::decode($content);
        } catch (RuntimeException $e) {
            throw new ImportFailedException(meta: ['reason' => $e->getMessage()]);
        }

        $declaredModule = TransferFile::module($file);
        if ($declaredModule !== $expectedModule) {
            throw new ImportFailedException(meta: ['reason' => 'module_mismatch:'.$declaredModule]);
        }

        return $file;
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
    private function persist(
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
