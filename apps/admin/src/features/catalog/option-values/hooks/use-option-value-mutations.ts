import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createOptionValue,
  deleteOptionValue,
  updateOptionValue,
} from '../api/option-values.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type OptionValue, type OptionValueForm } from '../data/schema'

/**
 * Create or update an option-value (nested under its parent option). The
 * API call, success toast and cache invalidation live here; the consuming
 * component only handles UI concerns (close dialog, reset form).
 */
export function useSaveOptionValue() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.OPTION_VALUE)

  return useMutation({
    mutationFn: ({
      optionId,
      id,
      data,
    }: {
      optionId: string
      id?: string
      data: OptionValueForm
    }) =>
      id
        ? updateOptionValue(optionId, id, data)
        : createOptionValue(optionId, data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('value'),
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
 * Delete a single option-value. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteOptionValue() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: ({ optionId, value }: { optionId: string; value: OptionValue }) =>
      deleteOptionValue(optionId, value.id.toString()),
    onSuccess: (_result, { value }) => {
      showSubmittedData(value, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, { value }) => {
      showSubmittedData(value, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many option-values (each knows its parent option). The
 * consuming component wraps mutateAsync in its own toast.promise for
 * loading/success/error feedback.
 */
export function useBulkDeleteOptionValues() {
  return useMutation({
    mutationFn: async (values: OptionValue[]) => {
      for (const value of values) {
        await deleteOptionValue(value.option_id.toString(), value.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
