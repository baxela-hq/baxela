import {
  deleteRequest,
  getRequest,
  patchRequest,
  postRequest,
} from '@/shared/lib/api-client'
import { type PaginatedResponse } from '@/shared/types/common.types'
import {
  type WebhookDelivery,
  type WebhookEndpoint,
  type WebhookEndpointWithSecret,
} from '../data/schema'

const BASE_URL = 'webhooks/admin'

export function fetchWebhookEndpoints(queryParams = {}) {
  return getRequest<PaginatedResponse<WebhookEndpoint>>(
    `${BASE_URL}/endpoints`,
    queryParams
  )
}

export function createWebhookEndpoint(input: {
  name: string
  url: string
  events: string[]
  is_active: boolean
}): Promise<WebhookEndpointWithSecret> {
  return postRequest<WebhookEndpointWithSecret, typeof input>(
    `${BASE_URL}/endpoints`,
    input
  )
}

export function updateWebhookEndpoint(
  id: number,
  input: {
    name: string
    url: string
    events: string[]
    is_active: boolean
  }
): Promise<{ data: WebhookEndpoint }> {
  return patchRequest<{ data: WebhookEndpoint }, typeof input>(
    `${BASE_URL}/endpoints/${id}`,
    input
  )
}

/** Archive-not-delete: history (deliveries) must survive. */
export function archiveWebhookEndpoint(id: number) {
  return deleteRequest<null>(`${BASE_URL}/endpoints/${id}`)
}

export function regenerateWebhookSecret(id: number) {
  return postRequest<WebhookEndpointWithSecret, Record<string, never>>(
    `${BASE_URL}/endpoints/${id}/regenerate-secret`,
    {}
  )
}

/** Sends a signed test delivery so the merchant can verify their receiver. */
export function testWebhookEndpoint(id: number) {
  return postRequest<{ data: WebhookEndpoint }, Record<string, never>>(
    `${BASE_URL}/endpoints/${id}/test`,
    {}
  )
}

export function fetchWebhookDeliveries(queryParams = {}) {
  return getRequest<PaginatedResponse<WebhookDelivery>>(
    `${BASE_URL}/deliveries`,
    queryParams
  )
}

export function retryWebhookDelivery(id: number) {
  return postRequest<null, Record<string, never>>(
    `${BASE_URL}/deliveries/${id}/retry`,
    {}
  )
}
