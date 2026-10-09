import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchPosts } from '../api/posts.api'
import { FeatureRoutes } from '../data/routes'
import { type Post } from '../data/schema'

/** Paginated posts list (server-side pagination/filter/sort via URL search). */
export function usePostsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Post>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchPosts(search),
    // placeholderData: (prev) => prev,
  })
}
