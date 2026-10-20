import { z } from 'zod'

export const refundCreateSchema = z.object({
  // platform money convention: two fraction digits
  amount: z
    .string()
    .min(1, 'required')
    .regex(/^\d{1,10}(\.\d{1,2})?$/, 'invalid'),
  reason: z.string().min(1, 'required').max(1000),
})
export type RefundCreate = z.infer<typeof refundCreateSchema>

export const refundResolveSchema = z.object({
  status: z.enum(['success', 'failed']),
  transaction_id: z
    .string()
    .max(255)
    .optional()
    .refine((value) => value && value.length > 0, {
      message: 'required',
      // only when confirming success
    }),
})
export type RefundResolve = z.infer<typeof refundResolveSchema>
