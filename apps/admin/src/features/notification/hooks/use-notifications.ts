import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchNotifications, fetchUnreadNotificationsCount } from '../api/notifications.api'
import { FeatureRoutes } from '../data/routes'
import { type Notification } from '../data/schema'

/** Paginated inbox list (server-side pagination/filter via URL search). */
export function useNotificationsList(search: Record<string, unknown> = {}, enabled = true) {
  return useQuery<PaginatedResponse<Notification>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'list', search],
    queryFn: () => fetchNotifications(search),
    enabled,
  })
}

/** Unread badge count, polled every 30s while the tab is visible. */
export function useUnreadNotificationsCount() {
  return useQuery({
    queryKey: [FeatureRoutes.CACHE_KEY, 'unread-count'],
    queryFn: () => fetchUnreadNotificationsCount(),
    refetchInterval: 30_000,
  })
}
