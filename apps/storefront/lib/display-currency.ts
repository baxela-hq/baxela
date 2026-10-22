import "server-only";

import { cache } from "react";
import { cookies } from "next/headers";

import { fetchActiveCurrencies } from "@/lib/api/site";
import {
  convertAmount,
  CURRENCY_COOKIE,
  type StoreCurrency,
} from "@/lib/currency";
import type { ApiActiveCurrency } from "@/lib/api/types";

/**
 * Server-side resolution of the visitor's display currency: the
 * `baxela-currency` cookie against the active-currency list, falling back
 * to the store default and then to a static USD when the backend is
 * unreachable. React cache() scopes the memoization to the CURRENT
 * REQUEST — the selection is per-visitor, so nothing may outlive a
 * request — and dedupes every caller (request config, root layout, header
 * switcher) into one shared fetch.
 */
export type DisplayCurrencyContext = {
  currency: StoreCurrency;
  /** Decimal string; 1 for the base currency. */
  rate: string;
  isBase: boolean;
  /** The store's base (default) currency — what charging happens in. */
  base: StoreCurrency;
  currencies: ApiActiveCurrency[];
  /** Convert a base-currency amount (major units) into display major units. */
  convert: (baseAmount: number) => number;
};

const FALLBACK_BASE: StoreCurrency = {
  code: "USD",
  symbol: "$",
  is_symbol_right: false,
  decimal_places: 2,
};

function toContext(
  selected: ApiActiveCurrency,
  base: ApiActiveCurrency | null,
  currencies: ApiActiveCurrency[],
): DisplayCurrencyContext {
  return {
    currency: {
      code: selected.code,
      symbol: selected.symbol,
      is_symbol_right: selected.is_symbol_right,
      decimal_places: selected.decimal_places,
    },
    rate: selected.exchange_rate,
    isBase: selected.is_default,
    base: base
      ? {
          code: base.code,
          symbol: base.symbol,
          is_symbol_right: base.is_symbol_right,
          decimal_places: base.decimal_places,
        }
      : FALLBACK_BASE,
    currencies,
    convert: (amount: number) =>
      convertAmount(
        amount,
        selected.exchange_rate,
        base?.decimal_places ?? 2,
        selected.decimal_places,
      ),
  };
}

/** The serializable subset safe to pass as a Client Component prop. */
export function toDisplayCurrencyData(
  context: DisplayCurrencyContext,
): Omit<DisplayCurrencyContext, "convert"> {
  return {
    currency: context.currency,
    rate: context.rate,
    isBase: context.isBase,
    base: context.base,
    currencies: context.currencies,
  };
}

export const resolveDisplayCurrency = cache(
  async (): Promise<DisplayCurrencyContext> => {
    const currencies = await fetchActiveCurrencies();

    if (!currencies || currencies.length === 0) {
      return {
        currency: FALLBACK_BASE,
        rate: "1",
        isBase: true,
        base: FALLBACK_BASE,
        currencies: [],
        convert: (amount) => amount,
      };
    }

    const base = currencies.find((row) => row.is_default) ?? currencies[0];

    // cookies() throws during static generation — those renders keep the
    // default currency
    let preferred: string | null = null;
    try {
      const store = await cookies();
      preferred = store.get(CURRENCY_COOKIE)?.value?.toUpperCase() ?? null;
    } catch {
      preferred = null;
    }

    const selected = currencies.find((row) => row.code === preferred) ?? base;

    return toContext(selected, base, currencies);
  },
);
