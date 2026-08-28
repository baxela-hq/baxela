import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type {
  AttributeTemplate,
  AttributeTemplateDetail,
  AttributeTemplateForm,
} from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'catalog/admin/attribute-templates'

export async function createAttributeTemplate(request: AttributeTemplateForm): Promise<AttributeTemplate> {
  const { data } = await postRequest<SingleResponse<AttributeTemplate>, AttributeTemplateForm>(BASE_URL, request)
  return data as AttributeTemplate
}

export function updateAttributeTemplate(id: string, data: AttributeTemplateForm): Promise<AttributeTemplate> {
  return patchRequest<AttributeTemplate, AttributeTemplateForm>(`${BASE_URL}/${id}`, data)
}

export function fetchAttributeTemplates(queryParams = {}) {
  return getRequest<PaginatedResponse<AttributeTemplate>>(BASE_URL, queryParams)
}

export async function fetchOneAttributeTemplate(id: string): Promise<AttributeTemplateDetail> {
  const { data } = await getRequest<SingleResponse<AttributeTemplateDetail>>(`${BASE_URL}/${id}`)
  return data as AttributeTemplateDetail
}

export function deleteAttributeTemplate(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
