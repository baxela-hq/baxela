import {
  deleteRequest,
  getRequest,
  postRequest,
} from '@/shared/lib/api-client'
import {
  type AllResponse,
  type SingleResponse,
} from '@/shared/types/common.types'

const BASE_URL = 'notification/admin/push-subscriptions'

export interface PushSubscription {
  id: number
  endpoint: string
  user_agent: string | null
  locale: string | null
  created_at: string | null
}

/** PushSubscription JSON as the browser hands it to us. */
export interface PushSubscriptionInput {
  endpoint: string
  keys: { p256dh?: string | null; auth?: string | null } | null
  user_agent?: string | null
  locale?: string | null
}

/** The VAPID public key browsers need before subscribing. Null = web push off. */
export function fetchVapidPublicKey() {
  return getRequest<SingleResponse<{ public_key: string | null }>>(
    'notification/webpush/vapid-public-key'
  )
}

export function fetchPushSubscriptions() {
  return getRequest<AllResponse<PushSubscription>>(BASE_URL)
}

export function savePushSubscription(subscription: PushSubscriptionInput) {
  return postRequest<SingleResponse<PushSubscription>, PushSubscriptionInput>(
    BASE_URL,
    subscription
  )
}

export function deletePushSubscription(endpoint: string) {
  return deleteRequest<void>(BASE_URL, { data: { endpoint } })
}
