import { useQuery } from '@tanstack/react-query'
import type { Currency } from '@/shared/types/locale.types'
import { fetchCurrencies } from '../api/currencies.api'

/**
 * Active currencies (dynamic, backend-driven). Shared query key
 * 'currencies' — every consumer dedupes into one request.
 */
export function useCurrencies() {
  return useQuery<Currency[]>({
    queryKey: ['currencies'],
    queryFn: () => fetchCurrencies(),
  })
}

/**
 * Full list including deactivated rows — the management tables and the
 * price formatter (historical records may reference a currency that was
 * deactivated later). Shares the 'currencies' key prefix, so the mutation
 * invalidations cover both lists.
 */
export function useAllCurrencies() {
  return useQuery<Currency[]>({
    queryKey: ['currencies', 'all'],
    queryFn: () => fetchCurrencies(true),
  })
}
