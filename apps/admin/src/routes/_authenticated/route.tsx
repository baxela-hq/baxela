import { createFileRoute, redirect } from '@tanstack/react-router'
import { AuthenticatedLayout } from '@/components/layout/authenticated-layout'
import { fetchAdminAccount } from '@/features/auth/sign-in/api/account.api'
import { useAuthStore } from '@/stores/auth-store'

export const Route = createFileRoute('/_authenticated')({
  beforeLoad: async ({ location }) => {
    // Gate navigation, not just API responses: without this the whole shell
    // renders for anonymous visitors (the 401 interceptor only fires on
    // pages that actually call the API). Token *validity* is still the
    // interceptor's job — an expired token is cleared on its first 401.
    const { user, accessToken, setUser } = useAuthStore.getState()
    if (!accessToken) {
      throw redirect({
        to: '/sign-in',
        search: { redirect: location.href },
      })
    }

    // Sessions persisted before the permissions field existed would look
    // like zero-permission users; backfill from the account endpoint. A
    // failure here leaves the session as-is — the API 401/403 interceptors
    // still protect every request.
    if (user && user.permissions === undefined) {
      try {
        const account = await fetchAdminAccount()
        setUser({ ...user, permissions: account.permissions ?? [] })
      } catch {
        // ignored on purpose
      }
    }
  },
  component: AuthenticatedLayout,
})
