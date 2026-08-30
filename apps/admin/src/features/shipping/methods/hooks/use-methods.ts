import { useQuery } from '@tanstack/react-query'
import { pickTranslation } from '@/shared/lib/locale'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchMethods } from '../api/methods.api'
import { FeatureRoutes } from '../data/routes'
import { type Method } from '../data/schema'

/** Paginated shipping methods list (server-side pagination/filter/sort via URL search). */
export function useMethodsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Method>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchMethods(search),
  })
}

/**
 * All shipping methods in one large fetch (method-select inputs on the
 * rates drawer). Cached under the shared 'all' key so every consumer
 * dedupes into one request.
 */
export function useMethodOptions() {
  const { data } = useQuery<PaginatedResponse<Method>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'all'],
    queryFn: () => fetchMethods({ per_page: 1000 }),
  })

  return data?.data ?? []
}

/** Display name of a method: the default-language translation falling back to the code. */
export function methodLabel(method: Method): string {
  return pickTranslation(method.translations)?.name || method.code
}
