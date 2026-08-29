import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchAttributeValues } from '../api/attribute-values.api'
import { FeatureRoutes } from '../data/routes'
import { type AttributeValue } from '../data/schema'

/** Paginated attribute-values list of one parent attribute (nested resource). */
export function useAttributeValuesList(
  attributeId: string,
  search: Record<string, unknown>
) {
  return useQuery<PaginatedResponse<AttributeValue>>({
    queryKey: [FeatureRoutes.CACHE_KEY, attributeId, search],
    queryFn: () => fetchAttributeValues(attributeId, search),
  })
}

/**
 * Predefined values of one attribute (select/multiselect inputs on the
 * product form). Cached under ['attribute-values', id].
 */
export function useAttributeValueOptions(attributeId: number) {
  return useQuery<PaginatedResponse<AttributeValue>>({
    queryKey: ['attribute-values', attributeId],
    queryFn: () => fetchAttributeValues(String(attributeId), { per_page: 1000 }),
  })
}
