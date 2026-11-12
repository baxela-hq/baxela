import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { toast } from 'sonner'
import {
  disableTwoFactor,
  fetchTwoFactorStatus,
  regenerateRecoveryCodes,
} from '../api/two-factor.api'
import { Locales } from '../data/routes'

const STATUS_KEY = ['auth', 'two-factor', 'status'] as const

export function useTwoFactorStatus() {
  return useQuery({
    queryKey: STATUS_KEY,
    queryFn: fetchTwoFactorStatus,
  })
}

/**
 * Self-service 2FA mutations (S.1 D7). Both disable and regenerate
 * require a valid TOTP or recovery code; the recovery codes returned
 * by regenerate are shown once, then discarded.
 */
export function useTwoFactorMutations() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.TWO_FACTOR)

  const onError = (err: unknown) => {
    toast.error(
      err instanceof ApiError ? err.message : tMessage('error-generic')
    )
  }

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: STATUS_KEY })
  }

  const disable = useMutation({
    mutationFn: (code: string) => disableTwoFactor(code),
    onSuccess: () => {
      toast.success(tMessage('disabled-success'))
      invalidate()
    },
    onError,
  })

  const regenerate = useMutation({
    mutationFn: (code: string) => regenerateRecoveryCodes(code),
    onSuccess: () => {
      invalidate()
    },
    onError,
  })

  return { disable, regenerate }
}
