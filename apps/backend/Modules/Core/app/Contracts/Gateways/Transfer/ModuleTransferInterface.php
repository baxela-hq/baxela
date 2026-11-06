<?php

namespace Modules\Core\Contracts\Gateways\Transfer;

use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;

/**
 * The module data-transfer standard: every module that supports import/
 * export of its own data implements this interface and declares the
 * implementation in its module config (`{lower}.transfer.class`), where
 * the Transfer module's registry picks it up.
 *
 * Contract rules:
 * - `sections()` returns the module's sections in dependency order — a
 *   section may only reference rows of sections before it, so a
 *   sequential replay can resolve references as it goes.
 * - Row payloads carry the exact JSON shapes the module's own admin
 *   create/update endpoints accept. Translations carry language codes
 *   (not ids); cross-record references carry the exporting store's
 *   source ids (the importer remaps them, the export itself stays
 *   id-only). Binary data is out of scope — media references stay
 *   identifiers, files themselves never travel in a transfer.
 * - `importRows()` replays rows through the module's own admin Actions
 *   (not raw model writes) so invariants, events and side effects behave
 *   exactly like a manual save. In dry-run nothing is written.
 */
interface ModuleTransferInterface
{
    /** Row key holding the exporting store's integer id. */
    public const string KEY_SOURCE_ID = 'source_id';

    /** Row key holding the request-shaped payload array. */
    public const string KEY_PAYLOAD = 'payload';

    /**
     * This module's sections, in dependency order.
     *
     * @return list<TransferSection>
     */
    public function sections(): array;

    /**
     * Export rows of one exportable section, ordered so parents precede
     * children.
     *
     * @param  string  $section  One of the section keys returned by sections().
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    public function exportRows(string $section): array;

    /**
     * Replay rows of one section: remap source ids, resolve language
     * codes, match existing records by natural key, then create or update
     * per the import strategy. Per-row failures are recorded on the
     * result, not thrown — one bad row must not sink the section.
     *
     * @param  string  $section  One of the section keys returned by sections().
     * @param  list<array<string, mixed>>  $rows  Rows as delivered inside the export file.
     * @param  ImportOptions  $options  Duplicate strategy + dry-run flag.
     */
    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult;
}
