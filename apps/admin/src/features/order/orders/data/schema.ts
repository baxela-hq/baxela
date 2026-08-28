import { z } from 'zod'

export const statuses = [
  'draft',
  'pending_payment',
  'paid',
  'processing',
  'shipped',
  'completed',
  'cancelled',
  'refunded',
];

export const orderSchema = z.object({
  id: z.number(),
  user_id: z.number(),
  total_amount: z.number(),
  description: z.string(),
  status: z.enum(statuses),
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
  description: z.string().optional(),
  note: z.string().optional(),
})
export type OrderForm = z.infer<typeof formSchema>


export const defaultValues: OrderForm = {
  status: '',
  description: '',
  note: '',
}





