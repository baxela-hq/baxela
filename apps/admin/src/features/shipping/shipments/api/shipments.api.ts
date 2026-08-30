import { getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { Shipment, ShipmentForm } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'shipping/admin/shipments'

/** The form keeps numeric fields as strings (house style) — convert at the API boundary. */
function toPayload(request: ShipmentForm) {
  return { ...request, order_id: Number(request.order_id) }
}

export async function createShipment(request: ShipmentForm): Promise<Shipment> {
  const { data } = await postRequest<SingleResponse<Shipment>, ReturnType<typeof toPayload>>(BASE_URL, toPayload(request))
  return data as Shipment
}

export function updateShipment(id: string, data: ShipmentForm): Promise<Shipment> {
  return patchRequest<Shipment, ReturnType<typeof toPayload>>(`${BASE_URL}/${id}`, toPayload(data))
}

export function fetchShipments(queryParams = {}) {
  return getRequest<PaginatedResponse<Shipment>>(BASE_URL, queryParams)
}

export async function fetchOneShipment(id: string): Promise<Shipment> {
  const { data } = await getRequest<SingleResponse<Shipment>>(`${BASE_URL}/${id}`)
  return data as Shipment
}
