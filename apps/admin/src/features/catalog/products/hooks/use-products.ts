import { useQuery } from '@tanstack/react-query'
import { pickTranslation } from '@/shared/lib/locale'
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

/**
 * All products in one large fetch (product-select inputs on other
 * modules' drawers, e.g. inventory stocks). Cached under the shared
 * 'all' key so every consumer dedupes into one request.
 */
export function useProductOptions() {
  const { data } = useQuery<PaginatedResponse<Product>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'all'],
    queryFn: () => fetchProducts({ per_page: 1000 }),
  })

  return data?.data ?? []
}

/** Display name of a product: the default-language translation falling back to the id. */
export function productLabel(product: Product): string {
  return pickTranslation(product.translations)?.title || `#${product.id}`
}
