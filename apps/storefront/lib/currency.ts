import type { NumberFormatOptions } from "next-intl";
import type { ApiCurrency } from "@/lib/api/types";

/**
 * Price formatting + display-currency conversion. Two layers:
 *
 *  - Formatting: the `price` number format is registered per-request in
 *    i18n/request.ts from the visitor's SELECTED currency (cookie), so
 *    every format.number(value, "price") renders in the display currency
 *    on server and client alike.
 *  - Conversion: prices, quotes and totals arrive from the API in the
 *    store's BASE currency; convertAmount() converts a base amount into
 *    the display currency before formatting. Charging always stays in the
 *    base currency — checkout only snapshots what was shown.
 *
 * The arithmetic mirrors the backend's Money::convert exactly (integer
 * minor units, half-up), implemented with BigInt so IRR-scale rates can
 * never drift through float math.
 */

const FALLBACK_CODE = "USD";

export const CURRENCY_COOKIE = "baxela-currency";

export type StoreCurrency = Pick<
  ApiCurrency,
  "code" | "symbol" | "is_symbol_right" | "decimal_places"
>;

export function toStoreCurrency(currency: ApiCurrency | null): StoreCurrency {
  if (!currency) {
    return {
      code: FALLBACK_CODE,
      symbol: "$",
      is_symbol_right: false,
      decimal_places: 2,
    };
  }

  return {
    code: currency.code,
    symbol: currency.symbol,
    is_symbol_right: currency.is_symbol_right,
    decimal_places: currency.decimal_places,
  };
}

// next-intl's format.number()/getFormatter() options type (an Intl superset).
export function currencyNumberOptions(
  currency: StoreCurrency | null,
): NumberFormatOptions {
  const decimals = currency?.decimal_places ?? 2;

  return {
    style: "currency",
    currency: currency?.code ?? FALLBACK_CODE,
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  };
}

/**
 * Convert a base-currency amount (major units, ≤2 fraction digits) into the
 * display currency's major units: parse both sides to integer minor units,
 * apply the rate (an 8-dp decimal string) with half-up rounding, and return
 * a display number for format.number(). Mirrors Money::convert in Core.
 */
export function convertAmount(
  baseAmount: number,
  rate: string,
  fromDecimals = 2,
  toDecimals = 2,
): number {
  if (!Number.isFinite(baseAmount) || baseAmount < 0) return baseAmount;

  const rateMatch = /^\d+(\.\d{1,8})?$/.exec(rate);
  if (!rateMatch) return baseAmount;

  // exponents stay ≤ 8, so number-side powers are exact before BigInt()
  const pow10 = (n: number): bigint => BigInt(10 ** n);

  // "600000.125" → 60000012500000n minor-of-minor units (8 dp)
  const [rateMajor, rateFraction = ""] = rate.split(".");
  const rateMinor =
    BigInt(rateMajor) * pow10(8) + BigInt(rateFraction.padEnd(8, "0"));

  const [baseMajor, baseFraction = ""] = baseAmount.toFixed(2).split(".");
  const baseMinor =
    BigInt(baseMajor) * pow10(fromDecimals) +
    BigInt((baseFraction + "00").slice(0, fromDecimals));

  const numerator = baseMinor * rateMinor * pow10(toDecimals);
  const denominator = pow10(8) * pow10(fromDecimals);

  const quotient = numerator / denominator;
  const remainder = numerator % denominator;
  const TWO = BigInt(2);
  const rounded =
    remainder * TWO >= denominator ? quotient + BigInt(1) : quotient;

  const scale = pow10(toDecimals);
  return Number(rounded / scale) + Number(rounded % scale) / Number(scale);
}

export function readCurrencyPreference(): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(
    /(?:^|; )baxela-currency=([A-Za-z]{3})(?:;|$)/,
  );
  return match ? match[1].toUpperCase() : null;
}

/** Mirror of the theme-mode cookie: one year, path-scoped, lax. */
export function writeCurrencyPreference(code: string): void {
  if (typeof document === "undefined") return;
  document.cookie = `${CURRENCY_COOKIE}=${code}; path=/; max-age=31536000; samesite=lax`;
}
