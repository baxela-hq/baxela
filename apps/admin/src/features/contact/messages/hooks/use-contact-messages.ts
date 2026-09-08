import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchContactMessages } from '../api/contact-messages.api'
import { FeatureRoutes } from '../data/routes'
import { type ContactMessage } from '../data/schema'

/** Inbox list. `search` is the route search object, so the faceted status
 * filter, the sender search, sort and pagination flow to the API verbatim. */
export function useContactMessagesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<ContactMessage>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchContactMessages(search),
  })
}
