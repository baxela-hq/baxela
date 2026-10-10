import type { Post } from '../../posts/data/schema'

/**
 * GET content/admin/featured — the admin-managed featured posts,
 * ordered by their saved position. The key name mirrors the backend
 * resource (`post`, the morph alias).
 */
export interface Featured {
  post: Post[]
}

/** PUT content/admin/featured — full sync, array order encodes position. */
export interface UpdateFeaturedPayload {
  post_ids: number[]
}
