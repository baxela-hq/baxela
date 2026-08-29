import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { createUser, deleteUser, updateUser } from '../api/users.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type User, type UserForm } from '../data/schema'

/**
 * Create or update a user. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveUser() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.USER)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: UserForm }) =>
      id ? updateUser(id, data) : createUser(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('user'),
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
 * Delete a single user. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteUser() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (user: User) => deleteUser(user.id.toString()),
    onSuccess: (_result, user) => {
      showSubmittedData(user, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, user) => {
      showSubmittedData(user, tMessage('error.general'))
    },
  })
}

/**
 * Fake bulk delete — only simulates the delay today. The consuming
 * component wraps mutateAsync in its own toast.promise.
 */
export function useBulkDeleteUsers() {
  return useMutation({
    mutationFn: async (_users: User[]) => {
      // TODO: real bulk delete (sequential deleteUser calls) once wired to the API
      await new Promise((resolve) => setTimeout(resolve, 2000))
    },
  })
}
