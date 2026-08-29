import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createAttributeValue,
  deleteAttributeValue,
  updateAttributeValue,
} from '../api/attribute-values.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type AttributeValue, type AttributeValueForm } from '../data/schema'

/**
 * Create or update an attribute-value (nested under its parent attribute). The
 * API call, success toast and cache invalidation live here; the consuming
 * component only handles UI concerns (close dialog, reset form).
 */
export function useSaveAttributeValue() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.ATTRIBUTE_VALUE)

  return useMutation({
    mutationFn: ({
      attributeId,
      id,
      data,
    }: {
      attributeId: string
      id?: string
      data: AttributeValueForm
    }) =>
      id
        ? updateAttributeValue(attributeId, id, data)
        : createAttributeValue(attributeId, data),
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
 * Delete a single attribute-value. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteAttributeValue() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: ({
      attributeId,
      value,
    }: {
      attributeId: string
      value: AttributeValue
    }) => deleteAttributeValue(attributeId, value.id.toString()),
    onSuccess: () => {
      // success stays silent today; the dialogs host refreshes the list
    },
    onError: (_err: unknown, { value }) => {
      showSubmittedData(value, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many attribute-values under one parent attribute. The
 * consuming component wraps mutateAsync in its own toast.promise for
 * loading/success/error feedback.
 */
export function useBulkDeleteAttributeValues() {
  return useMutation({
    mutationFn: async ({
      attributeId,
      values,
    }: {
      attributeId: string
      values: AttributeValue[]
    }) => {
      for (const value of values) {
        await deleteAttributeValue(attributeId, value.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
