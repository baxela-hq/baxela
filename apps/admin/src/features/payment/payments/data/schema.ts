import { z } from 'zod'

export const methods = ['manual', 'paypal', 'stripe'] as const
export type PaymentMethod = (typeof methods)[number]

export const statuses = ['pending', 'success', 'failed'] as const
export type PaymentStatus = (typeof statuses)[number]

/**
 * Mirrors PaymentStatusEnum on the backend: only pending payments can be
 * settled (confirmed or failed) by an admin.
 */
export function isSettleable(status: PaymentStatus): boolean {
  return status === 'pending'
}

export const paymentSchema = z.object({
  id: z.number(),
  order_id: z.number(),
  transaction_id: z.string().nullable(),
  method: z.enum(methods),
  amount: z.number(),
  status: z.enum(statuses),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Payment = z.infer<typeof paymentSchema>

export const updateSchema = z.object({
  status: z.enum(['success', 'failed']),
})
export type PaymentUpdate = z.infer<typeof updateSchema>
