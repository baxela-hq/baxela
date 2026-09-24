<?php

namespace Modules\Core\Support;

use InvalidArgumentException;

/**
 * Primitive money value utility — integer minor units only.
 *
 * Contract (impossible to misuse by construction):
 *   - integers are ALWAYS minor units (e.g. 10010 == 100.10 at precision 2)
 *   - major-unit amounts are ALWAYS decimal strings (e.g. '100.10')
 *
 * No PHP float ever passes through this class; all arithmetic is
 * string/integer based so domain money math stays exact.
 */
class Money
{
    /**
     * '100.10' → 10010. Accepts an optional fraction up to $precision digits.
     */
    public static function fromDecimal(string $decimal, int $precision = 2): int
    {
        if (! preg_match('/^\d+(\.\d{1,'.$precision.'})?$/', $decimal)) {
            throw new InvalidArgumentException(sprintf(
                'Malformed decimal amount "%s" (expected up to %d fraction digits, no sign).',
                $decimal,
                $precision
            ));
        }

        [$major, $minor] = array_pad(explode('.', $decimal, 2), 2, '');

        return ((int) $major * (10 ** $precision)) + (int) str_pad($minor, $precision, '0', STR_PAD_RIGHT);
    }

    /**
     * 10010 → '100.10'. Always emits exactly $precision fraction digits.
     */
    public static function toDecimal(int $minor, int $precision = 2): string
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Minor units cannot be negative.');
        }

        $scale = 10 ** $precision;

        return intdiv($minor, $scale).'.'.str_pad((string) ($minor % $scale), $precision, '0', STR_PAD_LEFT);
    }

    /**
     * Percentage of a minor-unit amount, rounded half-up once.
     *
     * $percent is a major-unit decimal string ('15.00' == 15%, NOT '0.15'),
     * matching the decimal(12,2) storage format of coupon values.
     */
    public static function applyPercent(int $minor, string $percent): int
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Minor units cannot be negative.');
        }

        // percent is stored with 2 fraction digits: '15.00' → 1500, so the
        // result is minor × 1500 / (100 × 100), rounded half-up
        $percentMinor = self::fromDecimal($percent);
        $denominator = 100 * 100;

        $quotient = intdiv($minor * $percentMinor, $denominator);
        $remainder = ($minor * $percentMinor) % $denominator;

        if ($remainder * 2 >= $denominator) {
            $quotient++;
        }

        return $quotient;
    }

    public static function min(int ...$values): int
    {
        return min($values);
    }
}
