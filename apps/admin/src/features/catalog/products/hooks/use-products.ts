import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchProducts } from '../api/products.api'
import { FeatureRoutes } from '../data/routes'
import { type Product } from '../data/schema'

/** Paginated products list (server-side pagination/filter/sort via URL search). */
export function useProductsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Product>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchProducts(search),
    // placeholderData: (prev) => prev,
  })
}
