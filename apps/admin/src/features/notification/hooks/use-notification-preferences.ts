import { useQuery } from '@tanstack/react-query'
import type { AllResponse } from '@/shared/types/common.types'
import { fetchNotificationPreferences } from '../api/notification-preferences.api'
import { FeatureRoutes } from '../data/routes'
import { type NotificationPreference } from '../data/schema'

/**
 * The caller's notification preferences: every admin-configurable
 * notification merged with per-channel overrides.
 */
export function useNotificationPreferences() {
  return useQuery<AllResponse<NotificationPreference>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'preferences'],
    queryFn: () => fetchNotificationPreferences(),
  })
}
