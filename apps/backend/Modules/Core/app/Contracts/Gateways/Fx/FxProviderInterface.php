<?php

namespace Modules\Core\Contracts\Gateways\Fx;

/**
 * An external FX rate source (S-Part E). Implementations return an
 * immutable snapshot whose numeric rates are EXACT decimal STRINGS —
 * no PHP float ever touches an FX value (financial-data invariant:
 * JSON numbers must not round-trip through binary floating point).
 */
interface FxProviderInterface
{
    /**
     * Fetch one rates snapshot relative to the given base currency.
     *
     * Implementations MUST validate the document structure and throw
     * FxProviderException with a sanitized error code on any failure;
     * raw provider bodies never surface in exceptions or logs.
     *
     * @param  string  $baseCode  ISO 4217 alpha-3 code, e.g. "USD"
     *
     * @throws FxProviderException
     */
    public function fetchRates(string $baseCode): FxRateSnapshot;
}
