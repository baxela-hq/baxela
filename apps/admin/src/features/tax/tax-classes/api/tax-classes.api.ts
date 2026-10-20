import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { TaxClass, TaxClassForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'tax/admin/tax-classes'

export async function createTaxClass(request: TaxClassForm): Promise<TaxClass> {
  const { data } = await postRequest<SingleResponse<TaxClass>, TaxClassForm>(BASE_URL, request)
  return data as TaxClass
}

export function updateTaxClass(id: string, data: TaxClassForm): Promise<TaxClass> {
  return patchRequest<TaxClass, TaxClassForm>(`${BASE_URL}/${id}`, data)
}

export function fetchTaxClasses(queryParams = {}) {
  return getRequest<PaginatedResponse<TaxClass>>(BASE_URL, queryParams)
}

export async function fetchOneTaxClass(id: string): Promise<TaxClass> {
  const { data } = await getRequest<SingleResponse<TaxClass>>(`${BASE_URL}/${id}`)
  return data as TaxClass
}

export function deleteTaxClass(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
