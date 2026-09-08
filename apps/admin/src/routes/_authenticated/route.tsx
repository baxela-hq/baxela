import { createFileRoute, redirect } from '@tanstack/react-router'
import { AuthenticatedLayout } from '@/components/layout/authenticated-layout'
import { useAuthStore } from '@/stores/auth-store'

export const Route = createFileRoute('/_authenticated')({
  beforeLoad: ({ location }) => {
    // Gate navigation, not just API responses: without this the whole shell
    // renders for anonymous visitors (the 401 interceptor only fires on
    // pages that actually call the API). Token *validity* is still the
    // interceptor's job — an expired token is cleared on its first 401.
    if (!useAuthStore.getState().accessToken) {
      throw redirect({
        to: '/sign-in',
        search: { redirect: location.href },
      })
    }
  },
  component: AuthenticatedLayout,
})
