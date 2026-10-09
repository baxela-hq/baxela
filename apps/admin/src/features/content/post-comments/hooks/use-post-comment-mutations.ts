import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createPostComment,
  deletePostComment,
  updatePostComment,
} from '../api/post-comments.api'
import { FeatureRoutes, Locales } from '../data/routes'
import {
  type PostComment,
  type PostCommentForm,
  type PostCommentReplyRequest,
  type PostCommentStatus,
} from '../data/schema'

/** The full record is sent on every update — the API patches all fields. */
export function useUpdatePostComment() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.POST_COMMENT)

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: PostCommentForm }) =>
      updatePostComment(id, data),
    onSuccess: async () => {
      toast.success(
        tMessage('success.record.updated', { name: tLabel('comment') })
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

/** Admin reply to a top-level comment — stored as approved. */
export function useReplyToPostComment() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.POST_COMMENT)

  return useMutation({
    mutationFn: ({ data }: { data: PostCommentReplyRequest }) =>
      createPostComment(data),
    onSuccess: async () => {
      toast.success(tMessage('success.record.created', { name: tLabel('reply') }))
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

/** Change a comment's status in place (approve/reject quick actions). */
export function useUpdatePostCommentStatus() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.POST_COMMENT)

  return useMutation({
    mutationFn: ({
      comment,
      status,
    }: {
      comment: PostComment
      status: PostCommentStatus
    }) =>
      updatePostComment(comment.id.toString(), {
        post_id: comment.post_id,
        parent_id: comment.parent_id,
        body: comment.body,
        status,
      }),
    onSuccess: async (_result, { status }) => {
      toast.success(
        tMessage('success.default', {
          name: tLabel('comment'),
          action: tLabel(status === 'approved' ? 'approved' : 'rejected'),
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

/** Sequentially set one status for many comments (bulk approve/reject). */
export function useBulkUpdatePostCommentStatus() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      comments,
      status,
    }: {
      comments: PostComment[]
      status: PostCommentStatus
    }) => {
      for (const comment of comments) {
        await updatePostComment(comment.id.toString(), {
          post_id: comment.post_id,
          parent_id: comment.parent_id,
          body: comment.body,
          status,
        })
      }
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}

export function useDeletePostComment() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: ({ comment }: { comment: PostComment }) =>
      deletePostComment(comment.id.toString()),
    onSuccess: (_result, { comment }) => {
      showSubmittedData(comment, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, { comment }) => {
      showSubmittedData(comment, tMessage('error.general'))
    },
  })
}

/** Sequentially delete many comments. */
export function useBulkDeletePostComments() {
  return useMutation({
    mutationFn: async (comments: PostComment[]) => {
      for (const comment of comments) {
        await deletePostComment(comment.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
