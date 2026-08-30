import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { Rate, RateForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'shipping/admin/rates'

export async function createRate(request: RateForm): Promise<Rate> {
  const { data } = await postRequest<SingleResponse<Rate>, RateForm>(BASE_URL, request)
  return data as Rate
}

export function updateRate(id: string, data: RateForm): Promise<Rate> {
  return patchRequest<Rate, RateForm>(`${BASE_URL}/${id}`, data)
}

export function fetchRates(queryParams = {}) {
  return getRequest<PaginatedResponse<Rate>>(BASE_URL, queryParams)
}

export async function fetchOneRate(id: string): Promise<Rate> {
  const { data } = await getRequest<SingleResponse<Rate>>(`${BASE_URL}/${id}`)
  return data as Rate
}

export function deleteRate(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
