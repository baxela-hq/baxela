import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { Method, MethodForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'shipping/admin/methods'

export async function createMethod(request: MethodForm): Promise<Method> {
  const { data } = await postRequest<SingleResponse<Method>, MethodForm>(BASE_URL, request)
  return data as Method
}

export function updateMethod(id: string, data: MethodForm): Promise<Method> {
  return patchRequest<Method, MethodForm>(`${BASE_URL}/${id}`, data)
}

export function fetchMethods(queryParams = {}) {
  return getRequest<PaginatedResponse<Method>>(BASE_URL, queryParams)
}

export async function fetchOneMethod(id: string): Promise<Method> {
  const { data } = await getRequest<SingleResponse<Method>>(`${BASE_URL}/${id}`)
  return data as Method
}

export function deleteMethod(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
