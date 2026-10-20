import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchReturns } from '../api/returns.api'
import { FeatureRoutes } from '../data/routes'
import { type OrderReturn } from '../data/schema'

/** Paginated returns list (server-side pagination/filter/sort via URL search). */
export function useReturnsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<OrderReturn>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchReturns(search),
  })
}
