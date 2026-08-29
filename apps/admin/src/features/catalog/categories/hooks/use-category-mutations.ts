import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createCategory,
  deleteCategory,
  updateCategory,
} from '../api/categories.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Category, type CategoryForm } from '../data/schema'

/**
 * Create or update a category. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveCategory() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.CATEGORY)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: CategoryForm }) =>
      id ? updateCategory(id, data) : createCategory(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('category'),
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
 * Delete a single category. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteCategory() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (category: Category) => deleteCategory(category.id.toString()),
    onSuccess: (_result, category) => {
      showSubmittedData(category, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, category) => {
      showSubmittedData(category, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many categories. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteCategories() {
  return useMutation({
    mutationFn: async (categories: Category[]) => {
      for (const category of categories) {
        await deleteCategory(category.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
