import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchPromotions } from '../api/promotions.api'
import { FeatureRoutes } from '../data/routes'
import { type Promotion } from '../data/schema'

/** Paginated promotions list (server-side pagination/filter/sort via URL search). */
export function usePromotionsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Promotion>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchPromotions(search),
  })
}
