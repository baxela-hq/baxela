import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { Promotion, PromotionPayload } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'discount/admin/promotions'

export async function createPromotion(request: PromotionPayload): Promise<Promotion> {
  const { data } = await postRequest<SingleResponse<Promotion>, PromotionPayload>(BASE_URL, request)
  return data as Promotion
}

export function updatePromotion(id: string, data: PromotionPayload): Promise<Promotion> {
  return patchRequest<Promotion, PromotionPayload>(`${BASE_URL}/${id}`, data)
}

export function fetchPromotions(queryParams = {}) {
  return getRequest<PaginatedResponse<Promotion>>(BASE_URL, queryParams)
}

export async function fetchOnePromotion(id: string): Promise<Promotion> {
  const { data } = await getRequest<SingleResponse<Promotion>>(`${BASE_URL}/${id}`)
  return data as Promotion
}

export function deletePromotion(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
