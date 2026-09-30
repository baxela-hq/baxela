import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { InventoryStock, InventoryStockForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'inventory/admin/inventory-stocks'

export async function createInventoryStock(request: InventoryStockForm): Promise<InventoryStock> {
  const { data } = await postRequest<SingleResponse<InventoryStock>, InventoryStockForm>(BASE_URL, request)
  return data as InventoryStock
}

export function updateInventoryStock(id: string, data: InventoryStockForm): Promise<InventoryStock> {
  return patchRequest<InventoryStock, InventoryStockForm>(`${BASE_URL}/${id}`, data)
}

export function fetchInventoryStocks(queryParams = {}) {
  return getRequest<PaginatedResponse<InventoryStock>>(BASE_URL, queryParams)
}

export async function fetchOneInventoryStock(id: string): Promise<InventoryStock> {
  const { data } = await getRequest<SingleResponse<InventoryStock>>(`${BASE_URL}/${id}`)
  return data as InventoryStock
}

export function deleteInventoryStock(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
