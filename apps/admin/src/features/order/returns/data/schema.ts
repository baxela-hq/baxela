import { z } from 'zod'

export const returnStatusSchema = z.enum([
  'requested',
  'approved',
  'rejected',
  'shipped',
  'received',
  'completed',
  'cancelled',
])
export type ReturnStatus = z.infer<typeof returnStatusSchema>

// what each status allows next (mirrors the backend state machine)
export const statusTransitions: Record<ReturnStatus, ReturnStatus[]> = {
  requested: ['approved', 'rejected', 'cancelled'],
  approved: ['shipped', 'cancelled'],
  shipped: ['received'],
  received: ['completed'],
  rejected: [],
  completed: [],
  cancelled: [],
}

export const returnSchema = z.object({
  id: z.number(),
  order_id: z.number(),
  order_code: z.string().nullable().optional(),
  user_id: z.number(),
  status: returnStatusSchema,
  reason: z.string(),
  note: z.string().nullable(),
  admin_note: z.string().nullable(),
  tracking_number: z.string().nullable(),
  stock_restored_at: z.string().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type OrderReturn = z.infer<typeof returnSchema>

export const updateSchema = z.object({
  status: returnStatusSchema,
  admin_note: z.string().max(1000).optional(),
  // only meaningful on the received transition
  restore_stock: z.boolean().optional(),
})
export type OrderReturnUpdate = z.infer<typeof updateSchema>
