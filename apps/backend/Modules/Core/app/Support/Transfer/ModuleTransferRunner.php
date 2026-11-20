<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
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
    public function __construct(
        private InstallationIdentity $installation,
        private TransferImportSession $session,
    ) {}

    /**
     * Build a module's transfer envelope: sections in the module's
     * declared dependency order, each filled with request-shaped rows.
     * Carries this installation's outbound identity (C1) — the additive
     * `source_installation` key that names the source store when the file
     * is imported elsewhere.
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
            TransferFormat::KEY_SOURCE_INSTALLATION => $this->installation->current(),
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
            'source_installation' => TransferFile::rawSourceInstallation($file),
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
     * The whole replay lives inside the destination-module import session
     * (C7): the module's named lock serializes concurrent imports into
     * its shared natural-key namespace, and the run context's ownership
     * hook guarantees no importer statement executes on a session other
     * than the lock-owning one. A lost session is fatal for the run (F28):
     * committed units stay committed and the audit row records
     * `lock_session_lost` — the retry is a plain re-import (idempotent by
     * remap and natural key).
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
        bool $allowRawIds = false,
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

        // Source-installation namespace (C1): a valid uuid imports into an
        // identified namespace; a legacy file (or a malformed declaration)
        // lands in the reserved unidentified bucket, where raw-id fallback
        // stays off unless explicitly requested (C2).
        $rawSource = TransferFile::rawSourceInstallation($file);
        $identified = TransferFile::isValidSourceInstallation($rawSource);
        $namespace = $identified ? $rawSource : TransferRemapInterface::UNIDENTIFIED;

        $options = new ImportOptions($strategy, $dryRun, $namespace, $identified, $allowRawIds);
        $importableBySection = [];
        foreach ($transfer->sections() as $section) {
            $importableBySection[$section->key] = $section->importable;
        }

        // The tally is filled by reference so a session-loss abort keeps
        // the partial counts for the audit row (committed units stay).
        $tally = [
            'sectionSummary' => [],
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'warnings' => $rawSource !== null && ! $identified ? ['invalid_source_installation'] : [],
        ];
        $sessionLost = false;

        try {
            $this->session->run($module, $namespace, $identified, function (TransferRunContext $context) use ($sections, $importableBySection, $options, $transfer, &$tally): void {
                foreach ($sections as $sectionKey => $rows) {
                    // Per-section ownership pre-check (F28): a lost session
                    // aborts before the next section's importRows() runs.
                    $context->assertOwnership();

                    $rows = array_values($rows);

                    if (! $importableBySection[$sectionKey]) {
                        $tally['skipped'] += count($rows);
                        $tally['warnings'][] = 'not_importable:'.$sectionKey;
                        $tally['sectionSummary'][$sectionKey] = ['created' => 0, 'updated' => 0, 'skipped' => count($rows), 'failed' => 0];

                        continue;
                    }

                    $result = $transfer->importRows($sectionKey, $rows, $options);
                    $counts = $result->counts();
                    $tally['sectionSummary'][$sectionKey] = $counts;

                    $tally['created'] += $counts['created'];
                    $tally['updated'] += $counts['updated'];
                    $tally['skipped'] += $counts['skipped'];
                    $tally['failed'] += $counts['failed'];
                    $tally['errors'] = array_merge($tally['errors'], $result->errors);
                    $tally['warnings'] = array_merge($tally['warnings'], $result->warnings);
                }
            });
        } catch (TransferLockSessionLostException) {
            $sessionLost = true;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $errorCap = (int) config('core.transfer.error_cap');
        $errors = array_slice($tally['errors'], 0, $errorCap);
        $warnings = array_values(array_unique($tally['warnings']));
        // Resolution diagnostics (C2): how often the legacy raw-id tier
        // carried a reference — the operator's signal for how much of the
        // restore leaned on the single-store assumption.
        $rawIdResolutions = count(array_filter(
            $tally['warnings'],
            fn (string $warning): bool => str_starts_with($warning, 'raw_id_fallback:'),
        ));

        if ($sessionLost) {
            $status = TransferImportStatusEnum::FAILED;
            $warnings[] = 'lock_session_lost';
        } else {
            $status = ($tally['failed'] > 0 && $tally['created'] + $tally['updated'] + $tally['skipped'] === 0)
                ? TransferImportStatusEnum::FAILED
                : TransferImportStatusEnum::COMPLETED;
        }

        // Control-plane audit write (F30): runs strictly after session
        // teardown — the ownership hook is inert here, and after a session
        // loss the write gets a fresh connection.
        if ($sessionLost) {
            DB::reconnect();
        }

        try {
            $record = $this->persist(
                $module,
                $filename,
                $status,
                $strategy,
                $dryRun,
                $totalRows,
                $tally['created'],
                $tally['updated'],
                $tally['skipped'],
                $tally['failed'],
                $durationMs,
                $errors,
                $tally['sectionSummary'],
                $warnings,
                $namespace,
                $sessionLost,
                $rawIdResolutions,
            );
        } catch (Throwable $e) {
            report($e);

            throw new ImportFailedException(meta: ['reason' => 'audit_persistence_failed']);
        }

        return [
            'id' => (int) $record->getKey(),
            'module' => $module,
            'status' => $status->value,
            'strategy' => $strategy->value,
            'dry_run' => $dryRun,
            'total_rows' => $totalRows,
            'created_count' => $tally['created'],
            'updated_count' => $tally['updated'],
            'skipped_count' => $tally['skipped'],
            'failed_count' => $tally['failed'],
            'duration_ms' => $durationMs,
            'sections' => $tally['sectionSummary'],
            'warnings' => $warnings,
            'errors' => $errors,
            'raw_id_resolutions' => $rawIdResolutions,
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
     * only this audit row is wrapped in its own transaction. Runs after a
     * session loss carry the failure reason in the summary; the caller
     * classifies audit-write failures as `audit_persistence_failed` (F30).
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
        string $sourceInstallation,
        bool $sessionLost,
        int $rawIdResolutions = 0,
    ): TransferImport {
        $summary = [
            'sections' => $sectionSummary,
            'warnings' => $warnings,
            // Diagnostics (C1/C2): which source namespace the file
            // imported into, how the run ended, and how often the legacy
            // raw-id tier carried a reference.
            'source_installation' => $sourceInstallation === TransferRemapInterface::UNIDENTIFIED ? null : $sourceInstallation,
            'raw_id_resolutions' => $rawIdResolutions,
        ];
        if ($sessionLost) {
            $summary['failure_reason'] = 'lock_session_lost';
        }

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
                TransferImportSchema::SUMMARY => $summary,
            ]);

            DB::commit();

            return $record;
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }
}
