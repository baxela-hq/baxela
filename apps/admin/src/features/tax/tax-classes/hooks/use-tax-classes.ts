import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchTaxClasses } from '../api/tax-classes.api'
import { FeatureRoutes } from '../data/routes'
import { type TaxClass } from '../data/schema'

/** Paginated tax classes list (server-side pagination/filter/sort via URL search). */
export function useTaxClassesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<TaxClass>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchTaxClasses(search),
  })
}

/**
 * All tax classes in one large fetch (class-select input on the rate
 * drawer). Cached under the shared 'all' key so every consumer dedupes
 * into one request.
 */
export function useTaxClassOptions() {
  const { data } = useQuery<PaginatedResponse<TaxClass>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'all'],
    queryFn: () => fetchTaxClasses({ per_page: 1000 }),
  })

  return data?.data ?? []
}
