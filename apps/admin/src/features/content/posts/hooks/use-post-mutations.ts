import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createPost,
  deletePost,
  updatePost,
} from '../api/posts.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Post, type PostPayload } from '../data/schema'

/**
 * Create or update a post (full-page form). Returns the saved post so the
 * consuming component can redirect on create / refresh the row on update.
 */
export function useSavePost() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.POST)

  return useMutation({
    mutationFn: ({ id, data }: { id: number | null; data: PostPayload }) =>
      id ? updatePost(id.toString(), data) : createPost(data),
    onSuccess: (post, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('post'),
        })
      )
      // not awaited: the create-path redirect must not wait for the list refetch
      queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
      return post
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}

/**
 * Delete a single post. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeletePost() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (post: Post) => deletePost(post.id.toString()),
    onSuccess: (_result, post) => {
      showSubmittedData(post, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, post) => {
      showSubmittedData(post, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many posts by id. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeletePosts() {
  return useMutation({
    mutationFn: async (ids: string[]) => {
      for (const id of ids) {
        await deletePost(id)
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
