<?php

namespace Modules\Core\Support\Transfer;

/**
 * The outcome of resolving one cross-module reference through the C2
 * tiers. Importers map it onto their ImportResult: `error` → recordFailed,
 * `warning` → warn (with the resolved id or a null-out per the column
 * policy), success → use `localId`.
 */
final class CrossModuleResolution
{
    public function __construct(
        public readonly ?int $localId,
        /** `raw_id_fallback:{id}`, `remap_dangling:{id}`, … — present on success-with-caveat. */
        public readonly ?string $warning,
        /** `unknown_variant:{id}`, `ref_unresolvable_legacy:{id}`, `ambiguous_*:{id}` — present on failure. */
        public readonly ?string $error,
    ) {}

    public static function resolved(int $localId, ?string $warning = null): self
    {
        return new self($localId, $warning, null);
    }

    public static function failure(string $error): self
    {
        return new self(null, null, $error);
    }

    /**
     * The column policy's null-out: the reference is dropped, the row
     * still imports (only valid for nullable references).
     */
    public static function nullified(string $warning): self
    {
        return new self(null, $warning, null);
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }
}
