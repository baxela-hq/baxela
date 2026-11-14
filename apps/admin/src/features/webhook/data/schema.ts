/**
 * Types from the gap-C.2 Webhooks module contracts (frozen backend):
 * WebhookEndpointResource, WebhookDeliveryResource, EventCatalog and
 * WebhookDeliveryStatusEnum. The secret is NEVER part of any fetch — it
 * exists only in the create/regenerate responses.
 */

export interface WebhookEndpoint {
  id: number
  name: string
  url: string
  events: string[]
  is_active: boolean
  is_archived: boolean
  consecutive_failures: number
  created_at: string
  updated_at: string
}

/** The create/regenerate response: the resource plus the once-only secret. */
export interface WebhookEndpointWithSecret {
  data: WebhookEndpoint
  secret: string
}

export interface WebhookDelivery {
  id: number
  event_id: number
  endpoint_id: number
  event_type: string
  status: string
  attempt_count: number
  attempts: unknown
  next_attempt_at: string | null
  response_status: number | null
  response_snippet: string | null
  error_category: string | null
  duration_ms: number | null
  delivered_at: string | null
  created_at: string
}

/** The v1 event catalog (backend EventCatalog::types) — selectable subscriptions. */
export const EVENT_TYPES = [
  'order.created',
  'order.paid',
  'order.cancelled',
  'order.shipped',
  'shipment.shipped',
  'refund.processed',
  'return.requested',
  'test',
] as const

export const DELIVERY_STATUSES = [
  'pending',
  'processing',
  'succeeded',
  'failed_retryable',
  'failed_permanent',
] as const
