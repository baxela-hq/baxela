import { getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { PaginatedResponse } from '@/shared/types/common.types'

export interface PaymentRefund {
  id: number
  uuid: string
  payment_id: number
  order_id: number
  amount: string
  reason: string
  status: 'pending' | 'success' | 'failed' | 'reconciliation_required'
  transaction_id: string | null
  refunded_by: number | null
  created_at: string
  updated_at: string
}

export function fetchPaymentRefunds(paymentId: string, queryParams = {}) {
  return getRequest<PaginatedResponse<PaymentRefund>>(
    `payment/admin/payments/${paymentId}/refunds`,
    queryParams
  )
}

export function createPaymentRefund(
  paymentId: string,
  data: { amount: string; reason: string }
) {
  return postRequest<PaymentRefund, { amount: string; reason: string }>(
    `payment/admin/payments/${paymentId}/refunds`,
    data
  )
}

export function resolvePaymentRefund(
  refundId: string,
  data: { status: 'success' | 'failed'; transaction_id?: string }
) {
  return patchRequest<PaymentRefund, { status: string; transaction_id?: string }>(
    `payment/admin/refunds/${refundId}/resolve`,
    data
  )
}
