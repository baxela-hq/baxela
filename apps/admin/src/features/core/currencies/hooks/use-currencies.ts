import { useQuery } from '@tanstack/react-query'
import type { Currency } from '@/shared/types/locale.types'
import { fetchCurrencies } from '../api/currencies.api'

/**
 * Available currencies (dynamic, backend-driven). Shared query key
 * 'currencies' — every consumer dedupes into one request.
 */
export function useCurrencies() {
  return useQuery<Currency[]>({
    queryKey: ['currencies'],
    queryFn: () => fetchCurrencies(),
  })
}
