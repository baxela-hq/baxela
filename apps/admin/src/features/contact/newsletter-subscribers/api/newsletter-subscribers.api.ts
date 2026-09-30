import {
  deleteRequest,
  getRequest,
  patchRequest,
} from '@/shared/lib/api-client'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { type NewsletterSubscriber, type NewsletterSubscriberStatus } from '../data/schema'

const BASE_URL = 'contact/admin/newsletter-subscribers'

export function fetchNewsletterSubscribers(queryParams = {}) {
  return getRequest<PaginatedResponse<NewsletterSubscriber>>(BASE_URL, queryParams)
}

// only the status is editable — subscriptions are created on the storefront
export function updateNewsletterSubscriberStatus(
  id: string,
  status: NewsletterSubscriberStatus
) {
  return patchRequest<NewsletterSubscriber, { status: NewsletterSubscriberStatus }>(
    `${BASE_URL}/${id}/status`,
    { status }
  )
}

export function deleteNewsletterSubscriber(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
