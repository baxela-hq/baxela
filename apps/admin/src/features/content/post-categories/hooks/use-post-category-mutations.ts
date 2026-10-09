import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createPostCategory,
  deletePostCategory,
  updatePostCategory,
} from '../api/post-categories.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type PostCategory, type PostCategoryForm } from '../data/schema'

/**
 * Create or update a post category. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSavePostCategory() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.POST_CATEGORY)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: PostCategoryForm }) =>
      id ? updatePostCategory(id, data) : createPostCategory(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('post_category'),
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
 * Delete a single post category. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeletePostCategory() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (category: PostCategory) => deletePostCategory(category.id.toString()),
    onSuccess: (_result, category) => {
      showSubmittedData(category, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, category) => {
      showSubmittedData(category, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many post categories. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeletePostCategories() {
  return useMutation({
    mutationFn: async (categories: PostCategory[]) => {
      for (const category of categories) {
        await deletePostCategory(category.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
