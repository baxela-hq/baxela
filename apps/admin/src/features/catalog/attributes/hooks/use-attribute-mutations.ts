import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createAttribute,
  deleteAttribute,
  updateAttribute,
} from '../api/attributes.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Attribute, type AttributeForm } from '../data/schema'

/**
 * Create or update an attribute. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveAttribute() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.ATTRIBUTE)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: AttributeForm }) =>
      id ? updateAttribute(id, data) : createAttribute(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('attribute'),
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
 * Delete a single attribute. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteAttribute() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (attribute: Attribute) => deleteAttribute(attribute.id.toString()),
    onSuccess: () => {
      // success stays silent today; the dialogs host refreshes the list
    },
    onError: (_err: unknown, attribute: Attribute) => {
      showSubmittedData(attribute, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many attributes. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteAttributes() {
  return useMutation({
    mutationFn: async (attributes: Attribute[]) => {
      for (const attribute of attributes) {
        await deleteAttribute(attribute.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
