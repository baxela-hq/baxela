<?php

namespace Modules\Core\Contracts\Gateways\Transfer;

/**
 * The persisted source-id → local-id remap ledger (C1) — the mechanism
 * that lets module imports run sequentially across separate files: an
 * importer of module B resolves its references into module A's rows via
 * the mappings A's import recorded, even when the destination's ids
 * diverged completely from the source store's.
 *
 * Namespaced by SOURCE installation (never the local one), so two
 * independent stores both carrying `user 42` import into one destination
 * without cross-contamination. Recording happens inside the import unit's
 * transaction (entity write + remap record are atomic, F11); resolution
 * is the first tier of reference resolution, before natural keys.
 */
interface TransferRemapInterface
{
    /**
     * The reserved namespace for legacy v1 files that carry no
     * `source_installation` key. Deliberately shared: interleaving imports
     * from several unidentified stores risks cross-contamination, which is
     * why the raw-id tier stays off in this namespace unless explicitly
     * requested (C2).
     */
    public const string UNIDENTIFIED = 'unidentified';

    /**
     * Record (or overwrite) the mapping for one imported row. Overwriting
     * is the convergence point of repeated restores: the newest mapping
     * for (namespace, module, entity, source id) wins. Callers run this
     * inside their write unit's transaction; dry runs never record.
     */
    public function record(string $sourceInstallation, string $module, string $entity, int $sourceId, int $localId): void;

    /**
     * The local id the given source id mapped to during this source
     * installation's last import — or null when unrecorded. Returns the
     * mapping even when the local row has since been deleted; consumers
     * verify existence through the owning module's gateway and treat a
     * dead mapping as dangling (fall through with a warning).
     */
    public function resolve(string $sourceInstallation, string $module, string $entity, int $sourceId): ?int;

    /**
     * Forget the mappings pointing at the given LOCAL rows of one
     * (namespace, module, entity) space (F23): when an UPDATE re-hydrates
     * a parent's children (delete + re-insert), the deleted children's
     * mappings must die in the same transaction — no surviving remap may
     * point at a deleted row.
     *
     * @param  array<int, int>  $localIds
     */
    public function forgetByLocalIds(string $sourceInstallation, string $module, string $entity, array $localIds): void;
}
