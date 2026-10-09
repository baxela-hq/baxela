import { createFileRoute } from '@tanstack/react-router'
import { PostComments } from '@/features/content/post-comments'

// Passthrough schema: keeps Laravel-style params (`filter[status]`,
// `filter[post_id]`, `sort`, `page`, `pageSize`) in the URL unvalidated,
// like sibling comment lists, while typing the search for the feature.
export const Route = createFileRoute('/_authenticated/content/post-comments/')({
  validateSearch: (search: Record<string, unknown>) => search,
  component: PostComments,
})
