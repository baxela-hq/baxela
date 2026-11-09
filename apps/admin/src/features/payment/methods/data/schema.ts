import { z } from 'zod'

/**
 * Built-in method slugs, for i18n label lookup only — never an
 * authoritative registry. The runtime list comes from the admin API
 * (which reflects the backend driver registry), so unknown keys of
 * extension-registered gateways are preserved rather than rejected.
 */
export const methodKeys = ['manual', 'paypal', 'stripe', 'adyen', 'nowpayments', 'mercadopago', 'checkoutcom', 'razorpay'] as const
export type MethodKey = (typeof methodKeys)[number]

export const paymentMethodSchema = z.object({
  id: z.number(),
  method: z.string(),
  is_active: z.boolean(),
  sort_order: z.number(),
  is_configured: z.boolean(),
  is_registered: z.boolean(),
  is_refundable: z.boolean(),
})
export type PaymentMethod = z.infer<typeof paymentMethodSchema>

export const updateSchema = z.object({
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
})
export type PaymentMethodUpdate = z.infer<typeof updateSchema>
