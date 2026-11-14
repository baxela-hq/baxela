import { useQuery } from '@tanstack/react-query'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { fetchWebhookDeliveries, fetchWebhookEndpoints } from '../api/webhooks.api'
import { FeatureRoutes } from '../data/routes'
import { type WebhookDelivery, type WebhookEndpoint } from '../data/schema'

/** Paginated endpoint list (server-side pagination via URL search). */
export function useWebhookEndpoints(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<WebhookEndpoint>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchWebhookEndpoints(search),
  })
}

/** One endpoint's delivery log (filter endpoint_id; refreshed after retries). */
export function useWebhookDeliveries(endpointId: number | null) {
  return useQuery<PaginatedResponse<WebhookDelivery>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'deliveries', endpointId],
    queryFn: () =>
      fetchWebhookDeliveries({
        'filter[endpoint_id]': endpointId ?? '',
        'page[size]': 25,
      }),
    enabled: endpointId !== null,
  })
}
