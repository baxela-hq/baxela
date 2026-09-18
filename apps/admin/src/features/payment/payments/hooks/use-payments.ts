import { useQuery } from '@tanstack/react-query'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { fetchPayments } from '../api/payments.api'
import { FeatureRoutes } from '../data/routes'
import { type Payment } from '../data/schema'

/** Paginated payments list (server-side pagination/filter/sort via URL search). */
export function usePaymentsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Payment>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchPayments(search),
  })
}
