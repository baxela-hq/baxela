import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createAttributeGroup,
  deleteAttributeGroup,
  updateAttributeGroup,
} from '../api/attribute-groups.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type AttributeGroup, type AttributeGroupForm } from '../data/schema'

/**
 * Create or update an attribute group. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveAttributeGroup() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.ATTRIBUTE_GROUP)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: AttributeGroupForm }) =>
      id ? updateAttributeGroup(id, data) : createAttributeGroup(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('attribute_group'),
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
 * Delete a single attribute group. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteAttributeGroup() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (group: AttributeGroup) =>
      deleteAttributeGroup(group.id.toString()),
    onSuccess: () => {
      // success stays silent today; the dialogs host refreshes the list
    },
    onError: (_err: unknown, group: AttributeGroup) => {
      showSubmittedData(group, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many attribute groups. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteAttributeGroups() {
  return useMutation({
    mutationFn: async (groups: AttributeGroup[]) => {
      for (const group of groups) {
        await deleteAttributeGroup(group.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
