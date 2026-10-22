"use client";

import { createContext, useContext, useMemo } from "react";

import { convertAmount } from "@/lib/currency";
import type { DisplayCurrencyContext } from "@/lib/display-currency";

/**
 * Client-side access to the request-resolved display currency (cookie +
 * active rates, resolved server-side in the [locale] layout). Server
 * components read resolveDisplayCurrency() directly — this context exists
 * so client components (cart, checkout, order history) convert and format
 * against the exact same selection.
 *
 * The provider receives only SERIALIZABLE data (functions cannot cross
 * the server→client prop boundary) and derives the convert helper from
 * it client-side — identical arithmetic to the server's resolver.
 */
const CurrencyContext = createContext<DisplayCurrencyContext | null>(null);

export function CurrencyProvider({
  data,
  children,
}: {
  data: Omit<DisplayCurrencyContext, "convert">;
  children: React.ReactNode;
}) {
  const value = useMemo<DisplayCurrencyContext>(
    () => ({
      ...data,
      convert: (amount: number) =>
        convertAmount(
          amount,
          data.rate,
          data.base.decimal_places,
          data.currency.decimal_places,
        ),
    }),
    [data],
  );

  return <CurrencyContext.Provider value={value}>{children}</CurrencyContext.Provider>;
}

export function useCurrency(): DisplayCurrencyContext {
  const context = useContext(CurrencyContext);

  if (!context) {
    throw new Error("useCurrency has to be used within <CurrencyProvider>");
  }

  return context;
}
