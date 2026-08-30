import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createMethod,
  deleteMethod,
  updateMethod,
} from '../api/methods.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Method, type MethodForm } from '../data/schema'

/**
 * Create or update a shipping method. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveMethod() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.METHOD)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: MethodForm }) =>
      id ? updateMethod(id, data) : createMethod(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('method'),
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
 * Delete a single method. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteMethod() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (method: Method) => deleteMethod(method.id.toString()),
    onSuccess: (_result, method) => {
      showSubmittedData(method, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, method) => {
      showSubmittedData(method, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many methods. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteMethods() {
  return useMutation({
    mutationFn: async (methods: Method[]) => {
      for (const method of methods) {
        await deleteMethod(method.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
