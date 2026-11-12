import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import {
  fetchAccountSessions,
  revokeAccountSession,
  revokeAllAccountSessions,
} from '../api/sessions.api'
import { Locales } from '../data/routes'

const SESSIONS_KEY = ['auth', 'sessions'] as const

export function useAccountSessions() {
  return useQuery({
    queryKey: SESSIONS_KEY,
    queryFn: fetchAccountSessions,
  })
}

/** Self-service session revocation (S.2). */
export function useAccountSessionMutations() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SESSIONS)
  const { tMessage: tCommon } = useAppTranslation('shared/common')

  const onError = (err: unknown) => {
    if (err instanceof ApiError) parseAndToastError(err)
    else toast.error(tCommon('error.general'))
  }

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: SESSIONS_KEY })
  }

  const revoke = useMutation({
    mutationFn: (sessionId: number) => revokeAccountSession(sessionId),
    onSuccess: () => {
      toast.success(tMessage('revoked-success'))
      invalidate()
    },
    onError,
  })

  const revokeAll = useMutation({
    mutationFn: () => revokeAllAccountSessions(),
    onSuccess: () => {
      // "Sign out everywhere" killed THIS token too — back to sign-in.
      window.location.href = '/sign-in'
    },
    onError,
  })

  return { revoke, revokeAll }
}
