import { z } from 'zod'

/**
 * Method keys mirror PaymentMethodEnum on the backend. The runtime list
 * comes from the admin API (which reflects the backend driver registry),
 * so unknown keys of future gateways are preserved rather than rejected.
 */
export const methodKeys = ['manual', 'paypal', 'stripe'] as const
export type MethodKey = (typeof methodKeys)[number]

export const paymentMethodSchema = z.object({
  id: z.number(),
  method: z.string(),
  is_active: z.boolean(),
  sort_order: z.number(),
  is_configured: z.boolean(),
  is_registered: z.boolean(),
})
export type PaymentMethod = z.infer<typeof paymentMethodSchema>

export const updateSchema = z.object({
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
})
export type PaymentMethodUpdate = z.infer<typeof updateSchema>
