import { useQuery } from '@tanstack/react-query'
import { type AllResponse } from '@/shared/types/common.types'
import { fetchPaymentMethods } from '../api/methods.api'
import { FeatureRoutes } from '../data/routes'
import { type PaymentMethod } from '../data/schema'

/**
 * Every payment method with its admin-managed activation and checkout
 * position. Rows are seeded by the backend for each registered driver,
 * so this list mirrors the code-level driver registry.
 */
export function usePaymentMethods() {
  return useQuery<AllResponse<PaymentMethod>>({
    queryKey: [FeatureRoutes.CACHE_KEY],
    queryFn: fetchPaymentMethods,
  })
}
