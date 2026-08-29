import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchAttributeGroups } from '../api/attribute-groups.api'
import { FeatureRoutes } from '../data/routes'
import { type AttributeGroup } from '../data/schema'

/** Paginated attribute-groups list (server-side pagination/filter/sort via URL search). */
export function useAttributeGroupsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<AttributeGroup>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchAttributeGroups(search),
  })
}

/**
 * All attribute groups in one large fetch (group-select inputs on the
 * attributes and attribute-templates drawers). Cached under the shared
 * 'all' key so every consumer dedupes into one request.
 */
export function useAttributeGroupOptions() {
  const { data } = useQuery<PaginatedResponse<AttributeGroup>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'all'],
    queryFn: () => fetchAttributeGroups({ per_page: 1000 }),
  })

  return data?.data ?? []
}
