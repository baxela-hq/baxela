import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchBrands } from '../api/brands.api'
import { FeatureRoutes } from '../data/routes'
import { type Brand } from '../data/schema'

/** Paginated brands list (server-side pagination/filter/sort via URL search). */
export function useBrandsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Brand>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchBrands(search),
  })
}

/**
 * Every brand in one large fetch, for brand-select inputs like the
 * product form. Cached and shared across consumers.
 */
export function useBrandsAll(): Brand[] {
  const { data } = useQuery<PaginatedResponse<Brand>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'all'],
    queryFn: () => fetchBrands({ per_page: 1000 }),
  })

  return useMemo(() => data?.data ?? [], [data])
}
