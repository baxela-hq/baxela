import type { NumberFormatOptions } from "next-intl";
import type { ApiCurrency } from "@/lib/api/types";

/**
 * Price formatting driven by the store's default currency instead of a
 * hardcoded USD. Server components call currencyNumberOptions() with the
 * fetchCurrency() result; client components use the CurrencyProvider hook.
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
