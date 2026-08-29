import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchItems, fetchOneOrder, fetchOrders } from '../api/orders.api'
import { FeatureRoutes } from '../data/routes'
import { type Order, type OrderItem } from '../data/schema'

/** Paginated orders list (server-side pagination/filter/sort via URL search). */
export function useOrdersList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Order>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchOrders(search),
    // placeholderData: (prev) => prev,
  })
}

/** Single order by id (show page). */
export function useOneOrder(id: string) {
  return useQuery<Order>({
    queryKey: [FeatureRoutes.CACHE_SINGLE_KEY, id],
    queryFn: () => fetchOneOrder(id),
    // placeholderData: (prev) => prev,
  })
}

/** Line items of one order (show page). */
export function useOrderItems(id: string) {
  return useQuery<OrderItem[]>({
    queryKey: [`order-id-${id}-items`],
    queryFn: () => fetchItems(id),
    // placeholderData: (prev) => prev,
  })
}

/** Refresh the current order on the show page after an update. */
export function useInvalidateOrder(id: string) {
  const queryClient = useQueryClient()
  return () =>
    queryClient.invalidateQueries({
      queryKey: [FeatureRoutes.CACHE_SINGLE_KEY, id],
    })
}
