import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createRate,
  deleteRate,
  updateRate,
} from '../api/rates.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Rate, type RateForm } from '../data/schema'

/**
 * Create or update a shipping rate. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveRate() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.RATE)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: RateForm }) =>
      id ? updateRate(id, data) : createRate(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('rate'),
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
 * Delete a single rate. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteRate() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (rate: Rate) => deleteRate(rate.id.toString()),
    onSuccess: (_result, rate) => {
      showSubmittedData(rate, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, rate) => {
      showSubmittedData(rate, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many rates. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteRates() {
  return useMutation({
    mutationFn: async (rates: Rate[]) => {
      for (const rate of rates) {
        await deleteRate(rate.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
