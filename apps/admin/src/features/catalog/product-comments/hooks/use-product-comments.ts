import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchProductComments } from '../api/product-comments.api'
import { FeatureRoutes } from '../data/routes'
import { type ProductComment } from '../data/schema'

/**
 * Moderation queue. `search` is the route search object, so a
 * `filter[product_id]` param scoped from the products table flows through
 * verbatim, alongside the faceted status filter, sort and pagination.
 */
export function useProductCommentsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<ProductComment>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchProductComments(search),
  })
}
