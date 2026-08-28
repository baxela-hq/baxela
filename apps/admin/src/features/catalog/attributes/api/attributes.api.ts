import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { Attribute, AttributeForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'catalog/admin/attributes'

export async function createAttribute(request: AttributeForm): Promise<Attribute> {
  const { data } = await postRequest<SingleResponse<Attribute>, AttributeForm>(BASE_URL, request)
  return data as Attribute
}

export function updateAttribute(id: string, data: AttributeForm): Promise<Attribute> {
  return patchRequest<Attribute, AttributeForm>(`${BASE_URL}/${id}`, data)
}

export function fetchAttributes(queryParams = {}) {
  return getRequest<PaginatedResponse<Attribute>>(BASE_URL, queryParams)
}

export async function fetchOneAttribute(id: string): Promise<Attribute> {
  const { data } = await getRequest<SingleResponse<Attribute>>(`${BASE_URL}/${id}`)
  return data as Attribute
}

export function deleteAttribute(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
