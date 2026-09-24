import { useMutation } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { useApplyUiLanguage } from '@/shared/hooks/use-apply-ui-language'
import { ApiError } from '@/shared/lib/api-error'
import { StorageUtility, StorageKeys } from '@/shared/lib/storage-utility'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { fetchAdminAccount } from '../api/account.api'
import { signIn } from '../api/sign-in.api'
import { type SignInRequest } from '../types/sign-in'

/**
 * Full sign-in flow: API call, staff gate, default language/currency
 * persistence, auth store writes and the post-login redirect. The component
 * only supplies credentials and the redirect target.
 */
export function useSignIn() {
  const navigate = useNavigate()
  const { setUser, setAccessToken, reset } = useAuthStore()
  const applyUiLanguage = useApplyUiLanguage()

  return useMutation({
    mutationFn: ({
      redirectTo: _redirectTo,
      ...credentials
    }: SignInRequest & { redirectTo?: string }) => signIn(credentials),
    onSuccess: async (response, { redirectTo }) => {
      // the token must be stored first so the account call is authenticated
      setAccessToken(response.token)

      // staff gate: an admin-scope permission check on the server, not a
      // payload flag — non-staff users get a 403 here
      let account
      try {
        account = await fetchAdminAccount()
      } catch {
        reset()
        toast.error("You're not allowed to see this page")
        return
      }

      try {
        toast.success('You logged in successfully')

        StorageUtility.setItem(
          StorageKeys.DEFAULT_LANGUAGE,
          response.settings.language
        )
        StorageUtility.setItem(
          StorageKeys.DEFAULT_CURRENCY,
          response.settings.currency
        )

        // the admin UI (translations + direction) follows the store default
        // language reported at sign-in
        applyUiLanguage(response.settings.language)

        // Set user and access token
        setUser({
          id: account.id,
          accountNo: 'ACC001',
          email: account.email,
          role: account.roles.map((role) => role.name),
          name: ' ', //TODO: set name
          exp: Date.now() + 30 * 24 * 60 * 60 * 1000, // 30*24 hours from now
        })
        // Redirect to the stored location or default to dashboard
        const targetPath = redirectTo || '/'
        await navigate({ to: targetPath, replace: true })
      } catch (err) {
        // A crash anywhere in the post-sign-in chain used to die silently
        // (the button just did nothing) — reset and tell the user instead.
        reset()
        toast.error(
          err instanceof Error
            ? `Sign-in could not be completed: ${err.message}`
            : 'Sign-in could not be completed'
        )
      }
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) {
        toast.error(err.message)
        return
      }
      // Non-API failures (e.g. a crashed client-side step such as device-id
      // generation) must not die in silence — they look like a dead button.
      toast.error(
        err instanceof Error ? err.message : 'Sign-in failed. Please try again.'
      )
    },
  })
}
