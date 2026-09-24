import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { updatePaymentMethod } from '../api/methods.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type PaymentMethodUpdate } from '../data/schema'

/**
 * Toggle a method's checkout availability or change its position. The
 * payload always carries both fields (fixed shape required by the API).
 */
export function useUpdatePaymentMethod() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PAYMENT_METHOD)

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: PaymentMethodUpdate }) =>
      updatePaymentMethod(id, data),
    onSuccess: async () => {
      toast.success(
        tMessage('success.record.updated', { name: tLabel('payment-method') })
      )
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}

/**
 * Reindex several methods after a position change; one toast for the
 * whole move instead of one per row.
 */
export function useReorderPaymentMethods() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PAYMENT_METHOD)

  return useMutation({
    mutationFn: ({
      updates,
    }: {
      updates: Array<{ id: number; data: PaymentMethodUpdate }>
    }) =>
      Promise.all(
        updates.map((update) => updatePaymentMethod(update.id, update.data))
      ),
    onSuccess: async () => {
      toast.success(
        tMessage('success.record.updated', { name: tLabel('payment-method') })
      )
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}
