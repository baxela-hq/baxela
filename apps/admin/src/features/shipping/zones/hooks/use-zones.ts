import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchZones } from '../api/zones.api'
import { FeatureRoutes } from '../data/routes'
import { type Zone } from '../data/schema'

/** Paginated shipping zones list (server-side pagination/filter/sort via URL search). */
export function useZonesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Zone>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchZones(search),
  })
}

/**
 * All shipping zones in one large fetch (zone-select inputs on the
 * rates drawer). Cached under the shared 'all' key so every consumer
 * dedupes into one request.
 */
export function useZoneOptions() {
  const { data } = useQuery<PaginatedResponse<Zone>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'all'],
    queryFn: () => fetchZones({ per_page: 1000 }),
  })

  return data?.data ?? []
}
