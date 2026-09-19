import { getDefaultCurrency } from '@/shared/lib/locale'
import { useCurrencies } from '@/features/core/currencies/hooks/use-currencies'

/**
 * Price formatter that resolves the symbol from a record's own currency
 * (order/payment snapshot), falling back to the shop-default currency when
 * the record has none.
 */
export function useFormatPrice() {
  const { data: currencies } = useCurrencies()
  const byId = new Map(
    (currencies ?? []).map((currency) => [currency.id, currency])
  )

  return (price: string | number, currencyId?: number | null) => {
    const currency =
      (currencyId != null ? byId.get(currencyId) : undefined) ??
      getDefaultCurrency()
    const symbol = currency?.symbol ?? '$'

    return currency?.is_symbol_right
      ? `${price} ${symbol}`
      : `${symbol}${price}`
  }
}
