<?php

namespace Modules\Core\Support\Transfer;

use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;

/**
 * Cross-module reference resolution, the C2 tiers applied uniformly:
 *
 *   1. Persisted remap — what a previous real import of the same source
 *      installation recorded, verified alive via the owning module's
 *      gateway (a dead mapping is dangling: warned, never trusted).
 *   2. Natural key — the payload-borne identity (email, order_code,
 *      coupon_code, uuid, SKU …) via the caller's lookup; wins over a
 *      colliding raw id and warns when both tiers point at different rows.
 *   3. Raw source id — legacy same-store tolerance. Automatic for
 *      identified files (the id space plausibly belongs to the same
 *      store); for legacy UNIDENTIFIED files it is gated behind the
 *      explicit `allow_raw_ids` request flag, because silently attaching
 *      to "whatever has this id here" is how unrelated stores'
 *      colliding ids capture each other's rows (F16).
 *   4. Column policy — the caller decides: fail the row (non-nullable
 *      identity refs) or null out with a warning (nullable refs).
 */
final class CrossModuleResolver
{
    public function __construct(private TransferRemapStore $remaps) {}

    /**
     * Resolve one reference.
     *
     * @param  string  $module  the OWNING module of the referenced entity
     *                          (e.g. `catalog` for variant ids)
     * @param  string  $entity  the referenced entity key (e.g. `variants`)
     * @param  int  $sourceId  the exporting store's id carried in the payload
     * @param  (callable(int): ?int)|null  $naturalKeyLookup  source-agnostic
     *                                                        identity probe returning the local id, if any (tier 2)
     * @param  (callable(int): bool)  $exists  liveness probe in the owning
     *                                         module — verifies remap hits and raw ids actually exist here
     * @param  bool  $nullable  whether an unresolved reference may null out
     */
    public function resolve(
        ImportOptions $options,
        string $module,
        string $entity,
        int $sourceId,
        ?callable $naturalKeyLookup,
        callable $exists,
        bool $nullable,
    ): CrossModuleResolution {
        // Tier 1: the previous import's ledger — most specific identity.
        $remapped = $this->remaps->resolve($options->sourceInstallation, $module, $entity, $sourceId);
        if ($remapped !== null) {
            if ($exists($remapped)) {
                return CrossModuleResolution::resolved($remapped);
            }

            // Stale ledger entry (the local row was deleted afterwards):
            // fall through, never trust a dead mapping.
        }

        // Tier 2: payload-borne natural identity — wins over raw ids.
        $natural = $naturalKeyLookup !== null ? $naturalKeyLookup($sourceId) : null;
        if ($natural !== null) {
            $warning = $remapped !== null && $remapped !== $natural
                ? "natural_key_overrides_remap:{$module}.{$entity}:{$sourceId}"
                : null;

            return CrossModuleResolution::resolved($natural, $warning);
        }

        // Tier 3: raw id, gated by the file's identity (F16).
        $rawAllowed = $options->identified || $options->allowRawIds;
        if (! $rawAllowed) {
            return $nullable
                ? CrossModuleResolution::nullified("ref_unresolvable_legacy:{$module}.{$entity}:{$sourceId}")
                : CrossModuleResolution::failure("ref_unresolvable_legacy:{$module}.{$entity}:{$sourceId}");
        }

        if ($exists($sourceId)) {
            return CrossModuleResolution::resolved(
                $sourceId,
                $remapped !== null
                    ? "raw_id_overrides_dangling_remap:{$module}.{$entity}:{$sourceId}"
                    : "raw_id_fallback:{$module}.{$entity}:{$sourceId}",
            );
        }

        // Tier 4: column policy.
        return $nullable
            ? CrossModuleResolution::nullified("unknown_{$entity}:{$sourceId}")
            : CrossModuleResolution::failure("unknown_{$entity}:{$sourceId}");
    }
}
