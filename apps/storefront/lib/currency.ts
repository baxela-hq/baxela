import type { NumberFormatOptions } from "next-intl";
import type { ApiCurrency } from "@/lib/api/types";

/**
 * Price formatting driven by the store's default currency instead of a
 * hardcoded USD. The `price` number format is registered in i18n/request.ts
 * from the /core/public/currency response; every consumer formats via
 * format.number(value, "price"). Visitor-facing multi-currency (switcher +
 * display conversion) is planned — see docs/plans/phase-1-alpha-hardening.md.
 */

const FALLBACK_CODE = "USD";

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
