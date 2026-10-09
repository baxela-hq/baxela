import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createPromotion,
  deletePromotion,
  updatePromotion,
} from '../api/promotions.api'
import { toPayload, type Promotion, type PromotionForm } from '../data/schema'
import { FeatureRoutes, Locales } from '../data/routes'

/**
 * Create or update a promotion. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (navigate back, reset form) in its per-call onSuccess.
 */
export function useSavePromotion() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PROMOTION)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: PromotionForm }) =>
      id
        ? updatePromotion(id, toPayload(data))
        : createPromotion(toPayload(data)),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('promotion'),
        })
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
 * Delete a single promotion. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeletePromotion() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (promotion: Promotion) => deletePromotion(promotion.id.toString()),
    onSuccess: (_result, promotion) => {
      showSubmittedData(promotion, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, promotion) => {
      showSubmittedData(promotion, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many promotions. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeletePromotions() {
  return useMutation({
    mutationFn: async (promotions: Promotion[]) => {
      for (const promotion of promotions) {
        await deletePromotion(promotion.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
