<?php

namespace Modules\Tax\Support;

/**
 * Offline VAT-identifier FORMAT validation (gap-C1c D2), isolated per
 * country so rules are replaceable/extendable. A passing check means
 * "plausible format" — NEVER registration verification; automatic
 * reverse charge defers to a verification policy (the seam is documented
 * on TaxGatewayInterface).
 */
final class VatIdValidator
{
    /**
     * Per-country patterns on the NORMALIZED value (uppercased, spaces
     * and hyphens stripped). Greece uses the EL prefix (the documented
     * exception — the country code GR does not prefix its VAT ids).
     */
    private const array PATTERNS = [
        'AT' => '/^ATU\d{8}$/',
        'BE' => '/^BE0\d{9}$/',
        'BG' => '/^BG\d{9,10}$/',
        'CY' => '/^CY\d{8}[A-Z]$/',
        'CZ' => '/^CZ\d{8,10}$/',
        'DE' => '/^DE\d{9}$/',
        'DK' => '/^DK\d{8}$/',
        'EE' => '/^EE\d{9}$/',
        'EL' => '/^EL\d{9}$/',
        'ES' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/',
        'FI' => '/^FI\d{8}$/',
        'FR' => '/^FR[A-Z0-9]{2}\d{9}$/',
        'HR' => '/^HR\d{11}$/',
        'HU' => '/^HU\d{8}$/',
        'IE' => '/^IE[A-Z0-9]{7,8}$/',
        'IT' => '/^IT\d{11}$/',
        'LT' => '/^LT\d{9,12}$/',
        'LU' => '/^LU\d{8}$/',
        'LV' => '/^LV\d{11}$/',
        'MT' => '/^MT\d{8}$/',
        'NL' => '/^NL\d{9}B\d{2}$/',
        'PL' => '/^PL\d{10}$/',
        'PT' => '/^PT\d{9}$/',
        'RO' => '/^RO\d{2,10}$/',
        'SE' => '/^SE\d{10}01$/',
        'SI' => '/^SI\d{8}$/',
        'SK' => '/^SK\d{10}$/',
    ];

    /** Country code -> the prefix its VAT ids actually carry (GR → EL). */
    private const array PREFIX_BY_COUNTRY = ['GR' => 'EL'];

    /**
     * Plausible format for the country? True, false, or null when the
     * country has no rule (stored without a validation claim — option 1,
     * no status field).
     */
    public function plausibleFormat(string $countryCode, string $vatId): ?bool
    {
        $country = strtoupper(trim($countryCode));
        $normalized = strtoupper(preg_replace('/[\s-]/', '', trim($vatId)) ?? '');

        if ($normalized === '' || strlen($country) !== 2) {
            return false;
        }

        // Greece: VAT ids carry the EL prefix (the documented
        // exception) — the GR country code resolves to the EL pattern.
        $patternKey = self::PREFIX_BY_COUNTRY[$country] ?? $country;

        if (! array_key_exists($patternKey, self::PATTERNS)) {
            return null; // no rule for this country — no claim either way
        }

        return (bool) preg_match(self::PATTERNS[$patternKey], $normalized);
    }

    /**
     * Normalized storage form: uppercased, separators stripped.
     */
    public function normalize(string $vatId): string
    {
        return strtoupper(preg_replace('/[\s-]/', '', trim($vatId)) ?? '');
    }

    /**
     * The prefix this country's VAT ids carry (for the mismatch check
     * handled inside plausibleFormat via the country-specific pattern).
     */
    public function prefixFor(string $countryCode): string
    {
        return self::PREFIX_BY_COUNTRY[strtoupper(trim($countryCode))] ?? strtoupper(trim($countryCode));
    }
}
