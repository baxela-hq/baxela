import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchOneTicket, fetchTickets } from '../api/tickets.api'
import { FeatureRoutes } from '../data/routes'
import { type Ticket } from '../data/schema'

/** Ticket inbox. `search` is the route search object, so the faceted status
 * filter, the subject search, sort and pagination flow to the API verbatim. */
export function useTicketsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Ticket>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchTickets(search),
  })
}

/** Single ticket with its conversation (show page). */
export function useOneTicket(id: string) {
  return useQuery<Ticket>({
    queryKey: [FeatureRoutes.CACHE_SINGLE_KEY, id],
    queryFn: () => fetchOneTicket(id),
  })
}

/** Refresh the ticket + list caches after a mutation on the show page. */
export function useInvalidateTicket(id: string) {
  const queryClient = useQueryClient()
  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_SINGLE_KEY, id],
      }),
      queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      }),
    ])
  }
}
