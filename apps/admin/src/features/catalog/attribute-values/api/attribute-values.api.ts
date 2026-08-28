import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { AttributeValue, AttributeValueForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'catalog/admin/attributes/$id/values'

export async function createAttributeValue(attributeId: string, request: AttributeValueForm): Promise<AttributeValue> {
  const { data } = await postRequest<SingleResponse<AttributeValue>, AttributeValueForm>(getUrl(attributeId), request)
  return data as AttributeValue
}

export function updateAttributeValue(attributeId: string, id: string, data: AttributeValueForm): Promise<AttributeValue> {
  return patchRequest<AttributeValue, AttributeValueForm>(getUrl(attributeId, id), data)
}

export function fetchAttributeValues(attributeId: string, queryParams = {}) {
  return getRequest<PaginatedResponse<AttributeValue>>(getUrl(attributeId), queryParams)
}

export function deleteAttributeValue(attributeId: string, id: string) {
  return deleteRequest(getUrl(attributeId, id))
}

function getUrl(attributeId: string, id?: string): string {
  const url = BASE_URL.replace('$id', attributeId)
  return typeof id !== 'undefined' ? `${url}/${id}` : url
}
