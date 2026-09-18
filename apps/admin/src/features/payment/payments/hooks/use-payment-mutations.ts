import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { FeatureRoutes as OrderFeatureRoutes } from '@/features/order/orders/data/routes'
import { updatePayment } from '../api/payments.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type PaymentUpdate } from '../data/schema'

/**
 * Settle a pending payment (success/failed). Confirming also drives the
 * order to paid server-side, so the orders caches are invalidated too.
 */
export function useSettlePayment() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PAYMENT)

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: PaymentUpdate }) =>
      updatePayment(id, data),
    onSuccess: async () => {
      toast.success(
        tMessage('success.record.updated', { name: tLabel('payment') })
      )
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
      await queryClient.invalidateQueries({
        queryKey: [OrderFeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}
