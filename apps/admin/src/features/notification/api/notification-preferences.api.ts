import { getRequest, patchRequest } from '@/shared/lib/api-client'
import { type AllResponse, type SingleResponse } from '@/shared/types/common.types'
import {
  type NotificationPreference,
  type NotificationPreferenceUpdate,
} from '../data/schema'

const BASE_URL = 'notification/admin/notification-preferences'

export function fetchNotificationPreferences() {
  return getRequest<AllResponse<NotificationPreference>>(BASE_URL)
}

export function updateNotificationPreference(form: NotificationPreferenceUpdate) {
  return patchRequest<SingleResponse<NotificationPreference>, NotificationPreferenceUpdate>(
    BASE_URL,
    form
  )
}
