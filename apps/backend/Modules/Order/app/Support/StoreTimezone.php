<?php

namespace Modules\Order\Support;

/**
 * Store-timezone resolver at the document boundary (D3). v1 reads the
 * application timezone — the Setting module's store timezone, applied at
 * boot. The indirection exists so a future multi-store/tenant process
 * cannot silently number documents in the wrong zone: only this class
 * needs to change.
 */
final class StoreTimezone
{
    /**
     * The document year for "now", in the store's timezone.
     */
    public static function year(): int
    {
        return (int) now(config('app.timezone'))->format('Y');
    }
}
