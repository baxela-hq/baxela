import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { TaxRate, TaxRateForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'tax/admin/tax-rates'

export async function createTaxRate(request: TaxRateForm): Promise<TaxRate> {
  const { data } = await postRequest<SingleResponse<TaxRate>, TaxRateForm>(BASE_URL, request)
  return data as TaxRate
}

export function updateTaxRate(id: string, data: TaxRateForm): Promise<TaxRate> {
  return patchRequest<TaxRate, TaxRateForm>(`${BASE_URL}/${id}`, data)
}

export function fetchTaxRates(queryParams = {}) {
  return getRequest<PaginatedResponse<TaxRate>>(BASE_URL, queryParams)
}

export async function fetchOneTaxRate(id: string): Promise<TaxRate> {
  const { data } = await getRequest<SingleResponse<TaxRate>>(`${BASE_URL}/${id}`)
  return data as TaxRate
}

export function deleteTaxRate(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
