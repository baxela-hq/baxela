import { getRequest, patchRequest } from '@/shared/lib/api-client'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'
import { type Notification } from '../data/schema'

const BASE_URL = 'notification/admin/notifications'

export function fetchNotifications(queryParams = {}) {
  return getRequest<PaginatedResponse<Notification>>(BASE_URL, queryParams)
}

export function fetchUnreadNotificationsCount() {
  return getRequest<SingleResponse<{ unread_count: number }>>(
    `${BASE_URL}/unread-count`
  )
}

export function markNotificationRead(id: number) {
  return patchRequest<SingleResponse<Notification>, Record<string, never>>(
    `${BASE_URL}/${id}/read`,
    {}
  )
}

export function markAllNotificationsRead() {
  return patchRequest<SingleResponse<{ unread_count: number }>, Record<string, never>>(
    `${BASE_URL}/read-all`,
    {}
  )
}
