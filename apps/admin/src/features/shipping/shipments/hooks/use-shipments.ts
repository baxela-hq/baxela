import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchShipments } from '../api/shipments.api'
import { FeatureRoutes } from '../data/routes'
import { type Shipment } from '../data/schema'

/** Paginated shipments list (server-side pagination/filter/sort via URL search). */
export function useShipmentsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Shipment>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchShipments(search),
  })
}
