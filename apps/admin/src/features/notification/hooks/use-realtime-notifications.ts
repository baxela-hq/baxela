import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { fetchAdminAccount } from '@/features/auth/sign-in/api/account.api'
import { useAuthStore } from '@/stores/auth-store'
import { connectEcho, disconnectEcho } from '@/shared/lib/echo'
import { FeatureRoutes } from '../data/routes'
import { type Notification } from '../data/schema'

interface RealtimeNotification extends Notification {
  unread_count: number
}

/**
 * Live unread badge + inbox refresh over the admin audience's private
 * channel — replaces the old 30s polling. Mounted once in the
 * authenticated layout; the connection opens with the session token and
 * closes on unmount (sign-out navigates away from the authenticated
 * shell). The channel is audience-scoped so storefront (user-audience)
 * rows for the same account never touch the admin badge.
 */
export function useRealtimeNotifications() {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)
  const token = useAuthStore((state) => state.accessToken)

  useEffect(() => {
    if (!token || !user || (user.id && user.tenant_id !== undefined)) return

    // Sessions persisted before the tenant-scoped channel went live lack
    // an id or store id; refetch the account once so the bell keeps
    // working without a forced re-login.
    let active = true
    void fetchAdminAccount()
      .then((account) => {
        if (!active) return
        useAuthStore.getState().setUser({ ...user, id: account.id, tenant_id: account.tenant_id })
      })
      .catch(() => {
        // 401s reset the session through the API client; nothing to do.
      })
    return () => {
      active = false
    }
  }, [token, user])

  useEffect(() => {
    if (!token || !user?.id || !user.tenant_id) return

    const echo = connectEcho(token)
    if (!echo) return

    echo
      .private(`tenant.${user.tenant_id}.notification.admin.${user.id}`)
      .listen('.notification.created', (payload: RealtimeNotification) => {
        queryClient.setQueryData([FeatureRoutes.CACHE_KEY, 'unread-count'], {
          data: { unread_count: payload.unread_count },
        })
        void queryClient.invalidateQueries({
          queryKey: [FeatureRoutes.CACHE_KEY, 'list'],
        })
      })

    return () => {
      disconnectEcho()
    }
  }, [token, user?.id, user?.tenant_id, queryClient])
}
