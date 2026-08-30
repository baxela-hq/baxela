import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { Zone, ZoneForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'shipping/admin/zones'

export async function createZone(request: ZoneForm): Promise<Zone> {
  const { data } = await postRequest<SingleResponse<Zone>, ZoneForm>(BASE_URL, request)
  return data as Zone
}

export function updateZone(id: string, data: ZoneForm): Promise<Zone> {
  return patchRequest<Zone, ZoneForm>(`${BASE_URL}/${id}`, data)
}

export function fetchZones(queryParams = {}) {
  return getRequest<PaginatedResponse<Zone>>(BASE_URL, queryParams)
}

export async function fetchOneZone(id: string): Promise<Zone> {
  const { data } = await getRequest<SingleResponse<Zone>>(`${BASE_URL}/${id}`)
  return data as Zone
}

export function deleteZone(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
