import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { Locales as SessionsLocales } from '@/features/auth/sessions/data/routes'
import {
  fetchUserSessions,
  revokeAllUserSessions,
  revokeUserSession,
} from '../api/users.api'
import { type User } from '../data/schema'
import { Locales } from '../data/routes'

const userSessionsKey = (userId: number) => ['users', userId, 'sessions'] as const

/**
 * Forced session revocation (S.2): the super-admin's view of a target
 * user's sessions, opened from the users table.
 */
export function useUserSessions(user: User | null) {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tMessage: tSession } = useAppTranslation(SessionsLocales.SESSIONS)

  const query = useQuery({
    queryKey: userSessionsKey(user?.id ?? 0),
    queryFn: () => fetchUserSessions(String(user?.id)),
    enabled: user !== null,
  })

  const onError = (err: unknown) => {
    if (err instanceof ApiError) parseAndToastError(err)
    else toast.error(tMessage('error.general'))
  }

  const invalidate = () => {
    if (user !== null) {
      queryClient.invalidateQueries({ queryKey: userSessionsKey(user.id) })
    }
  }

  const revoke = useMutation({
    mutationFn: (sessionId: number) =>
      revokeUserSession(String(user?.id), sessionId),
    onSuccess: () => {
      toast.success(tSession('revoked-success'))
      invalidate()
    },
    onError,
  })

  const revokeAll = useMutation({
    mutationFn: () => revokeAllUserSessions(String(user?.id)),
    onSuccess: () => {
      toast.success(tSession('revoked-all-success'))
      invalidate()
    },
    onError,
  })

  return { query, revoke, revokeAll }
}
