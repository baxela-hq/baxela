import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { AttributeGroup, AttributeGroupForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'catalog/admin/attribute-groups'

export async function createAttributeGroup(request: AttributeGroupForm): Promise<AttributeGroup> {
  const { data } = await postRequest<SingleResponse<AttributeGroup>, AttributeGroupForm>(BASE_URL, request)
  return data as AttributeGroup
}

export function updateAttributeGroup(id: string, data: AttributeGroupForm): Promise<AttributeGroup> {
  return patchRequest<AttributeGroup, AttributeGroupForm>(`${BASE_URL}/${id}`, data)
}

export function fetchAttributeGroups(queryParams = {}) {
  return getRequest<PaginatedResponse<AttributeGroup>>(BASE_URL, queryParams)
}

export async function fetchOneAttributeGroup(id: string): Promise<AttributeGroup> {
  const { data } = await getRequest<SingleResponse<AttributeGroup>>(`${BASE_URL}/${id}`)
  return data as AttributeGroup
}

export function deleteAttributeGroup(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
