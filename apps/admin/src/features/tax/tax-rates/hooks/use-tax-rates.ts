import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchTaxRates } from '../api/tax-rates.api'
import { FeatureRoutes } from '../data/routes'
import { type TaxRate } from '../data/schema'

/** Paginated tax rates list (server-side pagination/filter/sort via URL search). */
export function useTaxRatesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<TaxRate>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchTaxRates(search),
  })
}
