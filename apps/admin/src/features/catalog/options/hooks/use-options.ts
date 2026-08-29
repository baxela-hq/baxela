import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchOptions, fetchOneOption } from '../api/options.api'
import { FeatureRoutes } from '../data/routes'
import { type Option } from '../data/schema'

/** Paginated options list (server-side pagination/filter/sort via URL search). */
export function useOptionsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Option>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchOptions(search),
    // placeholderData: (prev) => prev,
  })
}

/** Single option by id (parent lookup on the option-values page). */
export function useOneOption(id: string) {
  return useQuery<Option>({
    queryKey: ['option', id],
    queryFn: () => fetchOneOption(id),
    // placeholderData: (prev) => prev,
  })
}

/**
 * All options in one large fetch (picker data for the product variant
 * matrix). Memoized so the array identity is stable between renders.
 */
export function useOptionsAll(): Option[] {
  const { data } = useQuery<PaginatedResponse<Option>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'all'],
    queryFn: () => fetchOptions({ per_page: 1000 }),
  })
  return useMemo(() => data?.data ?? [], [data])
}
