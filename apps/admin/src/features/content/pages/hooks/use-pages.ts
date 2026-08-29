import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchPages } from '../api/pages.api'
import { FeatureRoutes } from '../data/routes'
import { type Page } from '../data/schema'

/** Paginated pages list (server-side pagination/filter/sort via URL search). */
export function usePagesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Page>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchPages(search),
    // placeholderData: (prev) => prev,
  })
}
