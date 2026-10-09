import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchPostComments } from '../api/post-comments.api'
import { FeatureRoutes } from '../data/routes'
import { type PostComment } from '../data/schema'

/**
 * Moderation queue. `search` is the route search object, so a
 * `filter[post_id]` param scoped from the posts table flows through
 * verbatim, alongside the faceted status filter, sort and pagination.
 */
export function usePostCommentsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<PostComment>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchPostComments(search),
  })
}
