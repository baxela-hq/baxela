import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchNewsletterSubscribers } from '../api/newsletter-subscribers.api'
import { FeatureRoutes } from '../data/routes'
import { type NewsletterSubscriber } from '../data/schema'

/** Paginated newsletter subscribers list (server-side pagination/filter/sort via URL search). */
export function useNewsletterSubscribersList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<NewsletterSubscriber>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchNewsletterSubscribers(search),
  })
}
