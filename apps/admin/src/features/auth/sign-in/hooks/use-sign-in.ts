import { useMutation } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { ApiError } from '@/shared/lib/api-error'
import { StorageUtility, StorageKeys } from '@/shared/lib/storage-utility'
import { signIn } from '../api/sign-in.api'
import { type SignInRequest } from '../types/sign-in'

/**
 * Full sign-in flow: API call, admin gate, default language/currency
 * persistence, auth store writes and the post-login redirect. The component
 * only supplies credentials and the redirect target.
 */
export function useSignIn() {
  const navigate = useNavigate()
  const { setUser, setAccessToken } = useAuthStore()

  return useMutation({
    mutationFn: ({
      redirectTo: _redirectTo,
      ...credentials
    }: SignInRequest & { redirectTo?: string }) => signIn(credentials),
    onSuccess: async (response, { redirectTo }) => {
      // check if the user is admin
      if (!response.user.is_admin) {
        toast.error("You're not allowed to see this page")
        return
      }

      toast.success('You logged in successfully')

      StorageUtility.setItem(
        StorageKeys.DEFAULT_LANGUAGE,
        response.settings.language
      )
      StorageUtility.setItem(
        StorageKeys.DEFAULT_CURRENCY,
        response.settings.currency
      )

      // Set user and access token
      setUser({
        accountNo: 'ACC001',
        email: response.user.email,
        role: ['admin'],
        name: ' ', //TODO: set name
        exp: Date.now() + 30 * 24 * 60 * 60 * 1000, // 30*24 hours from now
      })
      setAccessToken(response.token)
      // Redirect to the stored location or default to dashboard
      const targetPath = redirectTo || '/'
      await navigate({ to: targetPath, replace: true })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) {
        toast.error(err.message)
      }
    },
  })
}
