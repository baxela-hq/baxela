import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createAttributeTemplate,
  deleteAttributeTemplate,
  updateAttributeTemplate,
} from '../api/attribute-templates.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type AttributeTemplate, type AttributeTemplateForm } from '../data/schema'

/**
 * Create or update an attribute template. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveAttributeTemplate() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.ATTRIBUTE_TEMPLATE)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: AttributeTemplateForm }) =>
      id ? updateAttributeTemplate(id, data) : createAttributeTemplate(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('attribute_template'),
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
 * Delete a single attribute template. No list invalidation on purpose — the
 * list refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteAttributeTemplate() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (template: AttributeTemplate) =>
      deleteAttributeTemplate(template.id.toString()),
    onSuccess: () => {
      // success stays silent today; the dialogs host refreshes the list
    },
    onError: (_err: unknown, template: AttributeTemplate) => {
      showSubmittedData(template, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many attribute templates. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteAttributeTemplates() {
  return useMutation({
    mutationFn: async (templates: AttributeTemplate[]) => {
      for (const template of templates) {
        await deleteAttributeTemplate(template.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
