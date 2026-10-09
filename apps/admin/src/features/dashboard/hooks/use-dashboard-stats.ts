import { useQuery } from '@tanstack/react-query'
import { fetchAuthStats, fetchOrderStats } from '../api/dashboard.api'
import { FeatureRoutes } from '../data/routes'
import { type AuthStats, type OrderStats } from '../data/schema'

/**
 * Dashboard aggregates don't churn like lists — a longer stale time keeps
 * tab switches and re-visits snappy without refetching.
 */
export function useOrderStats() {
  return useQuery<OrderStats>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'order-stats'],
    queryFn: fetchOrderStats,
    staleTime: 60_000,
  })
}

export function useAuthStats() {
  return useQuery<AuthStats>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'auth-stats'],
    queryFn: fetchAuthStats,
    staleTime: 60_000,
  })
}
