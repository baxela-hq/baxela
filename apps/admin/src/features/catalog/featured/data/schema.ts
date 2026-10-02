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

/**
 * Thumbnail of a product: the lowest-position image, mirroring how the
 * storefront picks its card image. Requires the images relation to be
 * loaded (?include=images) — otherwise falls back to undefined so the
 * caller can render the initials placeholder.
 */
export function firstProductImageUrl(product: Product): string | undefined {
  const images = product.images ?? []

  return [...images].sort((a, b) => a.position - b.position)[0]?.url
}
