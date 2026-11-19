<?php

namespace Modules\Notification\Support;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Core\DTOs\CurrencyDto;
use Modules\Core\Support\Money;

/**
 * Currency-aware amount rendering for order/payment notifications
 * (alpha.2 "currency everywhere").
 *
 * Two views, one rule set:
 *
 *  - forCustomer(): the display currency the customer chose at checkout,
 *    converted with the IMMUTABLE checkout-time rate from the order's
 *    snapshot — never the live rate — so the email matches what the
 *    storefront showed at purchase.
 *  - forCharge(): the transaction-time base (charging) currency with the
 *    ISO code appended. Payments are charge events; converting them into
 *    a display currency would imply money moved in that currency.
 *
 * Money stays exact end to end: the float arrives from an event whose
 * source columns are decimal(12,2) (magnitude < 10^10, exactly 2 fraction
 * digits), so sprintf('%.2F') reproduces the stored string exactly —
 * double ULP at 10^10 is ~1.9e-6, far below the 0.005 half-step — and
 * everything after is integer minor units via Money plus string-only
 * grouping (groupDecimals never round-trips through float, so formatting
 * cannot re-round what Money::convert produced).
 *
 * Precedence when metadata is missing (never silently relabel money):
 *  1. display row exists (active or inactive) → symbol/placement/precision from it;
 *  2. display row deleted → conversion still uses the snapshot rate, ISO code
 *     suffix at storage precision (documented: not an exact checkout rendering);
 *  3. snapshot code without rate (legacy rows) → base amount + base code;
 *  4. no snapshot → base amount + base code;
 *  5. base unresolvable → bare grouped number + warning log (see baseForOrder);
 *     the current default is never substituted for a known-but-unresolvable id.
 *
 * Stateless by design: currency metadata is resolved per call, never
 * cached across notification executions.
 */
final class AmountFormatter
{
    /**
     * orders.total_amount and payments.amount are decimal(12,2) — the
     * amount an event float denotes ALWAYS has exactly 2 fraction
     * digits, whatever any currency row's decimal_places says later.
     */
    private const int STORAGE_PRECISION = 2;

    private const int MAX_PRECISION = 8;

    public function __construct(private readonly CoreGatewayInterface $coreGateway) {}

    /**
     * Transaction-time base currency code for an order event. The order
     * row's currency_id is authoritative (it survives later default
     * flips and currency deactivation); a null id only occurs on legacy
     * events, where the store default is the best available answer.
     * A known id whose row cannot be resolved logs a warning — row 5,
     * never a silent substitution.
     */
    public function baseForOrder(?int $currencyId): ?string
    {
        if (! is_null($currencyId)) {
            $currency = $this->coreGateway->getCurrencyById($currencyId);

            if (is_null($currency)) {
                Log::warning('notification amount base currency unresolved', ['currency_id' => $currencyId]);

                return null;
            }

            return $currency->code;
        }

        $default = $this->coreGateway->getDefaultCurrency();

        if (is_null($default)) {
            // Legacy event (pre-snapshot) on an unconfigured store — render
            // the bare number, but never silently.
            Log::warning('notification amount base currency unresolved', [
                'currency_id' => null,
                'reason' => 'no transaction id and no default currency',
            ]);

            return null;
        }

        return $default->code;
    }

    /** Charge/admin view: base amount with the ISO code appended ("150.50 USD"). */
    public function forCharge(float $baseAmount, ?string $baseCode): string
    {
        $grouped = $this->groupedAtStoragePrecision($baseAmount);

        if (is_null($baseCode) || $baseCode === '') {
            return $grouped;
        }

        return $grouped.' '.strtoupper($baseCode);
    }

    /**
     * Customer view: the checkout snapshot currency when complete, base
     * + code otherwise (precedence rows 1-4 in the class docblock).
     */
    public function forCustomer(
        float $baseAmount,
        ?string $baseCode,
        ?string $displayCode,
        ?string $displayFxRate,
    ): string {
        if ($displayCode === null || $displayCode === '' || $displayFxRate === null || $displayFxRate === '') {
            return $this->forCharge($baseAmount, $baseCode);
        }

        // Metadata may resolve even for a deactivated currency — historical
        // reads must not lose symbol/precision (row 1).
        $display = $this->coreGateway->getCurrencyByCode(strtoupper($displayCode));

        try {
            $converted = Money::convert(
                $this->toMinor($baseAmount),
                $displayFxRate,
                self::STORAGE_PRECISION,
                self::precisionFrom($display),
            );
        } catch (\InvalidArgumentException) {
            // Malformed snapshot rate (only possible on hand-built legacy
            // payloads — real snapshots are decimal(16,8)): never fail the
            // notification over presentation; degrade honestly to base.
            Log::warning('notification amount malformed snapshot rate', [
                'display_currency' => $displayCode,
                'rate' => $displayFxRate,
            ]);

            return $this->forCharge($baseAmount, $baseCode);
        }

        $grouped = self::groupDecimals(Money::toDecimal($converted, self::precisionFrom($display)));

        if ($display instanceof CurrencyDto && ! is_null($display->symbol) && $display->symbol !== '') {
            return $display->is_symbol_right
                ? $grouped.' '.$display->symbol
                : $display->symbol.$grouped;
        }

        // Row 2: metadata row gone — ISO suffix at storage precision.
        return $grouped.' '.strtoupper($displayCode);
    }

    /**
     * Exact float → minor units at the fixed storage precision. The
     * boundary proof lives in the class docblock; %.2F is the
     * locale-independent fixed-point form.
     */
    private function toMinor(float $amount): int
    {
        return Money::fromDecimal(sprintf('%.2F', $amount), self::STORAGE_PRECISION);
    }

    private function groupedAtStoragePrecision(float $amount): string
    {
        return self::groupDecimals(Money::toDecimal($this->toMinor($amount), self::STORAGE_PRECISION));
    }

    /**
     * Display precision from the resolved display row, clamped to
     * 0-8 with a 2-dp fallback — malformed metadata never reaches
     * Money::convert or the decimal renderer as an unbounded value.
     */
    private static function precisionFrom(?CurrencyDto $currency): int
    {
        $raw = $currency?->decimal_places;

        if (is_null($raw) || ! ctype_digit($raw) || (int) $raw > self::MAX_PRECISION) {
            return 2;
        }

        return (int) $raw;
    }

    /**
     * Thousands grouping by string manipulation only: separators go into
     * the integer part of an already-exact decimal string, so rendering
     * can never re-round the conversion result.
     */
    private static function groupDecimals(string $decimal): string
    {
        $parts = explode('.', $decimal, 2);
        $grouped = preg_replace('/\B(?=(?:\d{3})+(?!\d))/', ',', $parts[0]);

        return isset($parts[1]) ? $grouped.'.'.$parts[1] : $grouped;
    }
}
