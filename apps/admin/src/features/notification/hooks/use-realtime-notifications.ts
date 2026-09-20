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
 * Live unread badge + inbox refresh over the user's private channel —
 * replaces the old 30s polling. Mounted once in the authenticated
 * layout; the connection opens with the session token and closes on
 * unmount (sign-out navigates away from the authenticated shell).
 */
export function useRealtimeNotifications() {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)
  const token = useAuthStore((state) => state.accessToken)

  useEffect(() => {
    if (!token || !user || user.id) return

    // Sessions signed in before the channel went live persist a user
    // without an id; refetch the account once so the bell keeps working
    // without a forced re-login.
    let active = true
    void fetchAdminAccount()
      .then((account) => {
        if (!active) return
        useAuthStore.getState().setUser({ ...user, id: account.id })
      })
      .catch(() => {
        // 401s reset the session through the API client; nothing to do.
      })
    return () => {
      active = false
    }
  }, [token, user])

  useEffect(() => {
    if (!token || !user?.id) return

    const echo = connectEcho(token)
    if (!echo) return

    echo
      .private(`user.${user.id}`)
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
  }, [token, user?.id, queryClient])
}
