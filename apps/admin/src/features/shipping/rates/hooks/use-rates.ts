import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchRates } from '../api/rates.api'
import { FeatureRoutes } from '../data/routes'
import { type Rate } from '../data/schema'

/** Paginated shipping rates list (server-side pagination/filter/sort via URL search). */
export function useRatesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Rate>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchRates(search),
  })
}
