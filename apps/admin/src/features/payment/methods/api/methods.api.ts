import { getRequest, patchRequest } from '@/shared/lib/api-client'
import {
  type AllResponse,
  type SingleResponse,
} from '@/shared/types/common.types'
import { type PaymentMethod, type PaymentMethodUpdate } from '../data/schema'

const BASE_URL = 'payment/admin/methods'

export function fetchPaymentMethods() {
  return getRequest<AllResponse<PaymentMethod>>(BASE_URL)
}

export function updatePaymentMethod(
  id: number,
  data: PaymentMethodUpdate
): Promise<SingleResponse<PaymentMethod>> {
  return patchRequest<SingleResponse<PaymentMethod>, PaymentMethodUpdate>(
    `${BASE_URL}/${id}`,
    data
  )
}
