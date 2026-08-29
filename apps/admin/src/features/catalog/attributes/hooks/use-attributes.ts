import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchAttributes, fetchOneAttribute } from '../api/attributes.api'
import { FeatureRoutes } from '../data/routes'
import { type Attribute } from '../data/schema'

/** Paginated attributes list (server-side pagination/filter/sort via URL search). */
export function useAttributesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Attribute>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchAttributes(search),
  })
}

/** Single attribute by id (parent lookup on the attribute-values page). */
export function useOneAttribute(id: string) {
  return useQuery<Attribute>({
    queryKey: ['attribute', id],
    queryFn: () => fetchOneAttribute(id),
  })
}

/**
 * All attributes in one large fetch (picker data on the product form).
 * The array is memoized so its identity is stable between renders.
 */
export function useAttributeOptions(): {
  attributes: Attribute[]
  isLoading: boolean
} {
  const { data, isLoading } = useQuery<PaginatedResponse<Attribute>>({
    queryKey: ['attributes', { per_page: 1000 }],
    queryFn: () => fetchAttributes({ per_page: 1000 }),
  })
  const attributes = useMemo(() => data?.data ?? [], [data])
  return { attributes, isLoading }
}
