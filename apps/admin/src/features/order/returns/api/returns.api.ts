import { deleteRequest, getRequest, patchRequest } from '@/shared/lib/api-client'
import type { OrderReturn, OrderReturnUpdate } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'order/admin/returns'

export function fetchReturns(queryParams = {}) {
  return getRequest<PaginatedResponse<OrderReturn>>(BASE_URL, queryParams)
}

export async function fetchOneReturn(id: string): Promise<OrderReturn> {
  const { data } = await getRequest<SingleResponse<OrderReturn>>(`${BASE_URL}/${id}`)
  return data as OrderReturn
}

export function updateReturn(id: string, data: OrderReturnUpdate): Promise<OrderReturn> {
  return patchRequest<OrderReturn, OrderReturnUpdate>(`${BASE_URL}/${id}`, data)
}

export function deleteReturn(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
