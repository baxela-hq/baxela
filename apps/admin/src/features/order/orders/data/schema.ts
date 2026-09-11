import { z } from 'zod'

export const statuses = [
  'pending',
  'processing',
  'shipped',
  'completed',
  'cancelled',
] as const;
export type OrderStatus = (typeof statuses)[number]

export const paymentStatuses = ['unpaid', 'paid', 'refunded'] as const
export type OrderPaymentStatus = (typeof paymentStatuses)[number]

/**
 * Mirrors OrderStatusEnum::transitions() on the backend — the drawer's
 * status select only offers the current status plus these.
 */
export const statusTransitions: Record<OrderStatus, OrderStatus[]> = {
  pending: ['processing', 'shipped', 'cancelled'],
  processing: ['shipped', 'cancelled'],
  shipped: ['completed'],
  completed: [],
  cancelled: [],
}

/**
 * Mirrors OrderPaymentStatusEnum::transitions() on the backend.
 */
export const paymentStatusTransitions: Record<OrderPaymentStatus, OrderPaymentStatus[]> = {
  unpaid: ['paid'],
  paid: ['refunded'],
  refunded: [],
}

export function allowedNextStatuses(current: OrderStatus): OrderStatus[] {
  return [current, ...statusTransitions[current]]
}

export function allowedNextPaymentStatuses(current: OrderPaymentStatus): OrderPaymentStatus[] {
  return [current, ...paymentStatusTransitions[current]]
}

export const orderSchema = z.object({
  id: z.number(),
  order_code: z.string(),
  user_id: z.number(),
  total_amount: z.number(),
  description: z.string(),
  status: z.enum(statuses),
  payment_status: z.enum(paymentStatuses),
  paid_at: z.string().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Order = z.infer<typeof orderSchema>

const _orderItemSchema = z.object({
  id: z.number(),
  variant_id: z.number(),
  quantity: z.number(),
  price_snapshot: z.string(),
  product_name_snapshot: z.string(),
})

export type OrderItem = z.infer<typeof _orderItemSchema>

export const formSchema = z.object({
  status: z.enum(statuses),
  payment_status: z.enum(paymentStatuses),
  description: z.string().optional(),
  note: z.string().optional(),
})
export type OrderForm = z.infer<typeof formSchema>


export const defaultValues: OrderForm = {
  status: 'pending',
  payment_status: 'unpaid',
  description: '',
  note: '',
}
