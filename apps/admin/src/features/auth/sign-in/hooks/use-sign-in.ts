import { useMutation } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { useApplyUiLanguage } from '@/shared/hooks/use-apply-ui-language'
import { ApiError } from '@/shared/lib/api-error'
import { StorageUtility, StorageKeys } from '@/shared/lib/storage-utility'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { fetchAdminAccount } from '../api/account.api'
import { signIn, verifyTwoFactor } from '../api/sign-in.api'
import { Locales } from '../data/routes'
import { type Settings, type SignInRequest } from '../types/sign-in'
import { useState } from 'react'

/**
 * Full sign-in flow including the 2FA challenge step (S.1): when the
 * backend returns two_factor_required, the form switches to an OTP
 * input; verifyTwoFactor completes the flow with the same post-sign-in
 * chain (staff gate → language → store → redirect). Forced enrollment
 * (super-admins) redirects to the enrollment wizard.
 */
export function useSignIn() {
  const navigate = useNavigate()
  const { setUser, setAccessToken, reset } = useAuthStore()
  const applyUiLanguage = useApplyUiLanguage()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const [challengeToken, setChallengeToken] = useState<string | null>(null)
  const [enrollmentToken, setEnrollmentToken] = useState<string | null>(null)

  /**
   * The shared post-authentication chain (staff gate → language →
   * store → redirect). Public so the forced-enrollment wizard can
   * hand over its sign-in payload once its recovery codes are
   * acknowledged.
   */
  const completeSignIn = async (
    response: { token: string; settings: Settings },
    redirectTo?: string,
  ) => {
    setAccessToken(response.token)

    let account
    try {
      account = await fetchAdminAccount()
    } catch {
      reset()
      toast.error(tMessage('error.forbidden'))
      return
    }

    try {
      toast.success(tMessage('success.sign-in'))

      StorageUtility.setItem(StorageKeys.DEFAULT_LANGUAGE, response.settings.language)
      StorageUtility.setItem(StorageKeys.DEFAULT_CURRENCY, response.settings.currency)
      applyUiLanguage(response.settings.language)

      setUser({
        id: account.id,
        accountNo: 'ACC001',
        email: account.email,
        role: account.roles.map((role) => role.name),
        permissions: account.permissions ?? [],
        name: ' ',
        exp: Date.now() + 30 * 24 * 60 * 60 * 1000,
      })

      const targetPath = redirectTo || '/'
      await navigate({ to: targetPath, replace: true })
    } catch (err) {
      reset()
      toast.error(
        err instanceof Error
          ? tMessage('error.sign-in-failed', { message: err.message })
          : tMessage('error.general'),
      )
    }
  }

  const signInMutation = useMutation({
    mutationFn: ({
      redirectTo: _redirectTo,
      ...credentials
    }: SignInRequest & { redirectTo?: string }) => signIn(credentials),
    onSuccess: async (response, { redirectTo }) => {
      if (response.two_factor_required && response.challenge_token) {
        setChallengeToken(response.challenge_token)
        return
      }

      if (response.two_factor_enrollment_required && response.challenge_token) {
        setEnrollmentToken(response.challenge_token)
        return
      }

      await completeSignIn(response, redirectTo)
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) {
        // S.4: account lockout gets a friendly, localized message. The
        // meta is typed loosely on ApiError — read defensively and fall
        // back to a message without the duration if it is absent/invalid.
        if (err.code === 'auth.sign_in.locked') {
          const minutes = Number(
            (err.meta as { retry_after_minutes?: unknown } | undefined)?.retry_after_minutes,
          )
          toast.error(
            Number.isFinite(minutes) && minutes > 0
              ? tMessage('error.account-locked', { minutes: String(minutes) })
              : tMessage('error.account-locked-generic'),
          )
          return
        }
        toast.error(err.message)
        return
      }
      toast.error(tMessage('error.general'))
    },
  })

  const verifyMutation = useMutation({
    mutationFn: ({ code }: { code: string; redirectTo?: string }) =>
      verifyTwoFactor(challengeToken!, code),
    onSuccess: async (response, { redirectTo }) => {
      setChallengeToken(null)
      await completeSignIn(response, redirectTo)
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) {
        toast.error(err.message)
        return
      }
      toast.error(tMessage('error.general'))
    },
  })

  return {
    signIn: signInMutation,
    verify: verifyMutation,
    completeSignIn,
    challengeToken,
    enrollmentToken,
    clearChallenge: () => {
      setChallengeToken(null)
      setEnrollmentToken(null)
    },
  }
}
