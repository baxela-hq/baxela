import { z } from 'zod'

export const SHIPMENT_STATUSES = [
  'pending',
  'packed',
  'shipped',
  'in_transit',
  'delivered',
  'failed',
] as const
export type ShipmentStatus = (typeof SHIPMENT_STATUSES)[number]

/**
 * Mirrors ShipmentStatusEnum::transitions() on the backend — the drawer's
 * status select only offers the current status plus these.
 */
export const statusTransitions: Record<ShipmentStatus, ShipmentStatus[]> = {
  pending: ['packed', 'failed'],
  packed: ['shipped', 'failed'],
  shipped: ['in_transit', 'failed'],
  in_transit: ['delivered', 'failed'],
  delivered: [],
  failed: [],
}

export function allowedNextStatuses(current: ShipmentStatus): ShipmentStatus[] {
  return [current, ...statusTransitions[current]]
}

export const shipmentSchema = z.object({
  id: z.number(),
  order_id: z.number(),
  carrier_name: z.string().nullable(),
  tracking_number: z.string().nullable(),
  tracking_url: z.string().nullable(),
  status: z.enum(SHIPMENT_STATUSES),
  shipped_at: z.string().nullable(),
  delivered_at: z.string().nullable(),
  notes: z.string().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Shipment = z.infer<typeof shipmentSchema>

export const formSchema = z.object({
  order_id: z.string().min(1, 'required'),
  carrier_name: z.string().nullable(),
  tracking_number: z.string().nullable(),
  tracking_url: z.string().nullable(),
  notes: z.string().nullable(),
  status: z.enum(SHIPMENT_STATUSES).nullable(),
})
export type ShipmentForm = z.infer<typeof formSchema>

export const defaultValues: ShipmentForm = {
  order_id: '',
  carrier_name: '',
  tracking_number: '',
  tracking_url: '',
  notes: '',
  status: 'pending',
}

export function buildEditValues(currentRow?: Shipment): ShipmentForm {
  if (!currentRow) return { ...defaultValues }

  return {
    order_id: currentRow.order_id.toString(),
    carrier_name: currentRow.carrier_name ?? '',
    tracking_number: currentRow.tracking_number ?? '',
    tracking_url: currentRow.tracking_url ?? '',
    notes: currentRow.notes ?? '',
    status: currentRow.status,
  }
}
