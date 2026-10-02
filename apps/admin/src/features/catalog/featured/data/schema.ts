import type { Category } from '../../categories/data/schema'
import type { Product } from '../../products/data/schema'

/**
 * GET catalog/admin/featured — the admin-managed storefront selections,
 * both sections ordered by their saved position. The key names mirror
 * the backend resource (`product` / `category`, the morph aliases).
 */
export interface Featured {
  product: Product[]
  category: Category[]
}

/** PUT catalog/admin/featured — full sync, array order encodes position. */
export interface UpdateFeaturedPayload {
  product_ids: number[]
  category_ids: number[]
}
