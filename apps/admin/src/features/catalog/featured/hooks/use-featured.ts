import { useQuery } from '@tanstack/react-query'
import { fetchFeatured } from '../api/featured.api'
import { FeatureRoutes } from '../data/routes'
import type { Featured } from '../data/schema'

/** The current featured products/categories with their saved order. */
export function useFeatured() {
  return useQuery<Featured>({
    queryKey: [FeatureRoutes.CACHE_KEY],
    queryFn: fetchFeatured,
  })
}
