import {
  deleteRequest,
  getRequest,
  patchRequest,
} from '@/shared/lib/api-client'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { type ContactMessage, type ContactMessageStatus } from '../data/schema'

const BASE_URL = 'contact/admin/messages'

export function fetchContactMessages(queryParams = {}) {
  return getRequest<PaginatedResponse<ContactMessage>>(BASE_URL, queryParams)
}

// only the status is editable — message content is read-only
export function updateContactMessageStatus(
  id: string,
  status: ContactMessageStatus
) {
  return patchRequest<ContactMessage, { status: ContactMessageStatus }>(
    `${BASE_URL}/${id}/status`,
    { status }
  )
}

export function deleteContactMessage(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
