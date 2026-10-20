import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  fetchPaymentRefunds,
  createPaymentRefund,
  resolvePaymentRefund,
} from '../api/refunds.api'
import { FeatureRoutes } from '../data/routes'
import { type RefundCreate, type RefundResolve } from '../data/refund-schema'
/**
 * Issue a refund against a successful payment (ledger-backed, amount
 * validated against the remaining refundable balance server-side).
 */
export function useCreateRefund() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: RefundCreate }) =>
      createPaymentRefund(id, data),
    onSuccess: async () => {
      toast.success('Refund recorded')
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
      await queryClient.invalidateQueries({ queryKey: ['payment-refunds'] })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error('Refund failed')
    },
  })
}

/** Refund history for one payment (also powers the history dialog). */
export function useRefundsList(paymentId: string) {
  return useQuery({
    queryKey: ['payment-refunds', paymentId],
    queryFn: () => fetchPaymentRefunds(paymentId),
    enabled: !!paymentId,
  })
}

/** Resolve an ambiguous (reconciliation_required) refund row. */
export function useResolveRefund() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: RefundResolve }) =>
      resolvePaymentRefund(id, data),
    onSuccess: async () => {
      toast.success('Refund resolved')
      await queryClient.invalidateQueries({ queryKey: ['payment-refunds'] })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error('Resolve failed')
    },
  })
}
