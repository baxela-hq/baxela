import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  deleteReturn,
  updateReturn,
} from '../api/returns.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type OrderReturn, type OrderReturnUpdate } from '../data/schema'

/**
 * Transition a return (approve/reject/receive/complete/cancel). The API
 * call, success toast and cache invalidation live here; the consuming
 * component only handles UI concerns in its per-call onSuccess.
 */
export function useUpdateReturn() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.RETURN)

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: OrderReturnUpdate }) =>
      updateReturn(id, data),
    onSuccess: async (_result, { data }) => {
      toast.success(
        tMessage('success.record.updated', {
          name: `${tLabel('return')} (${data.status})`,
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
 * Delete a single return. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteReturn() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (returnRow: OrderReturn) => deleteReturn(returnRow.id.toString()),
    onSuccess: (_result, returnRow) => {
      showSubmittedData(returnRow, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, returnRow) => {
      showSubmittedData(returnRow, tMessage('error.general'))
    },
  })
}

/** Sequentially delete many returns. */
export function useBulkDeleteReturns() {
  return useMutation({
    mutationFn: async (returns: OrderReturn[]) => {
      for (const returnRow of returns) {
        await deleteReturn(returnRow.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
