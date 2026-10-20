<?php

namespace Modules\Tax\Support;

use InvalidArgumentException;

/**
 * Exact, integer-only tax arithmetic. No PHP float ever participates:
 * rates arrive as storage-format decimal strings and are parsed to
 * integer rate-units; money is minor units throughout.
 */
class TaxMath
{
    /**
     * Rate-units are percent × 10^4 (5.1250% → 51250), so the exact tax
     * is base × rateUnits / 10^6.
     */
    private const int RATE_SCALE = 10 ** 4;

    private const int RATE_DIVISOR = 10 ** 6;

    /** Rates are validated to 0–100% at write time; enforced again here. */
    private const int MAX_RATE_UNITS = 100 * self::RATE_SCALE;

    /**
     * Keeps base × rateUnits well inside PHP_INT_MAX (10^12 × 10^6 < 10^18).
     * decimal(12,2) columns cannot store more anyway.
     */
    private const int MAX_BASE_MINOR = 10 ** 12;

    /**
     * Keeps allocation multiplies (discount × amount) inside PHP int range;
     * both operands stay under 2^31 (~2.1 billion minor units ≈ $21M).
     */
    private const int MAX_ALLOCATABLE_MINOR = 2 ** 31 - 1;

    /**
     * '8.5', '8.50', '8.5000' → 85000. Accepts 0–4 fraction digits.
     */
    public static function rateUnits(string $rateDecimal): int
    {
        if (! preg_match('/^\d{1,3}(\.\d{0,4})?$/', $rateDecimal)) {
            throw new InvalidArgumentException(sprintf('Malformed tax rate "%s".', $rateDecimal));
        }

        [$major, $fraction] = array_pad(explode('.', $rateDecimal, 2), 2, '');

        return ((int) $major * self::RATE_SCALE) + (int) str_pad($fraction, 4, '0', STR_PAD_RIGHT);
    }

    /**
     * Tax on a base in minor units at integer rate-units, rounded half-up
     * once per line: intdiv(base × units + 500_000, 1_000_000).
     */
    public static function taxOf(int $baseMinor, int $rateUnits): int
    {
        if ($baseMinor < 0) {
            throw new InvalidArgumentException('Tax base cannot be negative.');
        }

        if ($rateUnits < 0 || $rateUnits > self::MAX_RATE_UNITS) {
            throw new InvalidArgumentException(sprintf('Rate units %d out of range.', $rateUnits));
        }

        if ($baseMinor > self::MAX_BASE_MINOR) {
            throw new InvalidArgumentException('Tax base exceeds the supported maximum.');
        }

        return intdiv($baseMinor * $rateUnits + (self::RATE_DIVISOR / 2), self::RATE_DIVISOR);
    }

    /**
     * Deterministic largest-remainder allocation of a discount across
     * line amounts (both minor units). The sum of the result equals
     * min(discount, Σ amounts); remainder ties resolve toward the lower
     * key so the outcome never depends on input ordering.
     *
     * @param  array<int, int>  $amounts  keyed by variant id
     * @return array<int, int>  allocated discount per key (missing keys get 0)
     */
    public static function allocate(int $discountMinor, array $amounts): array
    {
        $result = array_fill_keys(array_keys($amounts), 0);

        if ($discountMinor <= 0 || $amounts === []) {
            return $result;
        }

        foreach ($amounts as $amount) {
            if ($amount < 0 || $amount > self::MAX_ALLOCATABLE_MINOR) {
                throw new InvalidArgumentException('Allocation amount out of supported range.');
            }
        }

        $total = array_sum($amounts);
        if ($total <= 0) {
            return $result;
        }

        // Coupon validation bounds the discount to the subtotal; clamp
        // defensively so the allocation can never go negative per line
        $discountMinor = min($discountMinor, $total);

        if ($discountMinor > self::MAX_ALLOCATABLE_MINOR) {
            throw new InvalidArgumentException('Allocated discount exceeds the supported maximum.');
        }

        $remainders = [];
        foreach ($amounts as $key => $amount) {
            $product = $discountMinor * $amount;
            $result[$key] = intdiv($product, $total);
            $remainders[$key] = $product % $total;
        }

        // Distribute the lost minor units to the largest remainders;
        // ties resolve toward the lower key (ascending variant id)
        $toDistribute = $discountMinor - array_sum($result);
        uksort($remainders, fn (int $a, int $b) => $a <=> $b);
        arsort($remainders);

        foreach (array_keys(array_slice($remainders, 0, $toDistribute, true)) as $key) {
            $result[$key]++;
        }

        return $result;
    }
}
