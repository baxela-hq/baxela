import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createOption,
  deleteOption,
  updateOption,
} from '../api/options.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Option, type OptionForm } from '../data/schema'

/**
 * Create or update an option. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveOption() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.OPTION)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: OptionForm }) =>
      id ? updateOption(id, data) : createOption(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('option'),
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
 * Delete a single option. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteOption() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (option: Option) => deleteOption(option.id.toString()),
    onSuccess: () => {
      // success stays silent today; the dialogs host refreshes the list
    },
    onError: (_err: unknown, option: Option) => {
      showSubmittedData(option, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many options. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteOptions() {
  return useMutation({
    mutationFn: async (options: Option[]) => {
      for (const option of options) {
        await deleteOption(option.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
