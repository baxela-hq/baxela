import { getRequest, patchRequest } from '@/shared/lib/api-client'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { type Payment, type PaymentUpdate } from '../data/schema'

const BASE_URL = 'payment/admin/payments'

export function fetchPayments(queryParams = {}) {
  return getRequest<PaginatedResponse<Payment>>(BASE_URL, queryParams)
}

export function updatePayment(
  id: string,
  data: PaymentUpdate
): Promise<Payment> {
  return patchRequest<Payment, PaymentUpdate>(`${BASE_URL}/${id}`, data)
}
