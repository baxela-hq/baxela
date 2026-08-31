import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createProductComment,
  deleteProductComment,
  updateProductComment,
} from '../api/product-comments.api'
import { FeatureRoutes, Locales } from '../data/routes'
import {
  type ProductComment,
  type ProductCommentForm,
  type ProductCommentReplyRequest,
  type ProductCommentStatus,
} from '../data/schema'

/** The full record is sent on every update — the API patches all fields. */
export function useUpdateProductComment() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT_COMMENT)

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: ProductCommentForm }) =>
      updateProductComment(id, data),
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
export function useReplyToProductComment() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT_COMMENT)

  return useMutation({
    mutationFn: ({ data }: { data: ProductCommentReplyRequest }) =>
      createProductComment(data),
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
export function useUpdateProductCommentStatus() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT_COMMENT)

  return useMutation({
    mutationFn: ({
      comment,
      status,
    }: {
      comment: ProductComment
      status: ProductCommentStatus
    }) =>
      updateProductComment(comment.id.toString(), {
        product_id: comment.product_id,
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
export function useBulkUpdateProductCommentStatus() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      comments,
      status,
    }: {
      comments: ProductComment[]
      status: ProductCommentStatus
    }) => {
      for (const comment of comments) {
        await updateProductComment(comment.id.toString(), {
          product_id: comment.product_id,
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

export function useDeleteProductComment() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: ({ comment }: { comment: ProductComment }) =>
      deleteProductComment(comment.id.toString()),
    onSuccess: (_result, { comment }) => {
      showSubmittedData(comment, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, { comment }) => {
      showSubmittedData(comment, tMessage('error.general'))
    },
  })
}

/** Sequentially delete many comments. */
export function useBulkDeleteProductComments() {
  return useMutation({
    mutationFn: async (comments: ProductComment[]) => {
      for (const comment of comments) {
        await deleteProductComment(comment.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
