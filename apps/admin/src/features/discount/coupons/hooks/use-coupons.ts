import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchCoupons } from '../api/coupons.api'
import { FeatureRoutes } from '../data/routes'
import { type Coupon } from '../data/schema'

/** Paginated coupons list (server-side pagination/filter/sort via URL search). */
export function useCouponsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Coupon>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchCoupons(search),
  })
}
