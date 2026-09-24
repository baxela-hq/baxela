import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { Coupon, CouponPayload } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'discount/admin/coupons'

export async function createCoupon(request: CouponPayload): Promise<Coupon> {
  const { data } = await postRequest<SingleResponse<Coupon>, CouponPayload>(BASE_URL, request)
  return data as Coupon
}

export function updateCoupon(id: string, data: CouponPayload): Promise<Coupon> {
  return patchRequest<Coupon, CouponPayload>(`${BASE_URL}/${id}`, data)
}

export function fetchCoupons(queryParams = {}) {
  return getRequest<PaginatedResponse<Coupon>>(BASE_URL, queryParams)
}

export async function fetchOneCoupon(id: string): Promise<Coupon> {
  const { data } = await getRequest<SingleResponse<Coupon>>(`${BASE_URL}/${id}`)
  return data as Coupon
}

export function deleteCoupon(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
