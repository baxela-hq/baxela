import { z } from 'zod'

export const couponTypeEnum = z.enum(['percent', 'fixed'])
export type CouponType = z.infer<typeof couponTypeEnum>

export const couponSchema = z.object({
  id: z.number(),
  code: z.string(),
  name: z.string().nullable(),
  type: couponTypeEnum,
  // percent: 15.00 means 15% off; fixed: major-unit amount off the subtotal
  value: z.string(),
  max_discount_amount: z.string().nullable(),
  min_order_amount: z.string().nullable(),
  starts_at: z.string().nullable(),
  ends_at: z.string().nullable(),
  usage_limit: z.number().nullable(),
  per_user_limit: z.number().nullable(),
  usage_count: z.number(),
  is_active: z.boolean(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Coupon = z.infer<typeof couponSchema>

export const formSchema = z
  .object({
    code: z.string().min(1, 'required'),
    name: z.string().nullable(),
    type: couponTypeEnum,
    value: z.string().min(1, 'required'),
    max_discount_amount: z.string().nullable(),
    min_order_amount: z.string().nullable(),
    starts_at: z.date().nullable(),
    ends_at: z.date().nullable(),
    usage_limit: z.number().nullable(),
    per_user_limit: z.number().nullable(),
    is_active: z.boolean(),
  })
  .superRefine((values, ctx) => {
    if (values.type === 'percent' && Number(values.value) > 100) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['value'],
      })
    }
    if (values.ends_at && values.starts_at && values.ends_at <= values.starts_at) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['ends_at'],
      })
    }
  })
export type CouponForm = z.infer<typeof formSchema>

/** Wire format: dates as ISO-8601 UTC strings, empty money fields as null. */
export type CouponPayload = {
  code: string
  name: string | null
  type: CouponType
  value: string
  max_discount_amount: string | null
  min_order_amount: string | null
  starts_at: string | null
  ends_at: string | null
  usage_limit: number | null
  per_user_limit: number | null
  is_active: boolean
}

export function toPayload(form: CouponForm): CouponPayload {
  return {
    code: form.code.trim().toUpperCase(),
    name: form.name?.trim() ? form.name.trim() : null,
    type: form.type,
    value: form.value,
    max_discount_amount: form.max_discount_amount || null,
    min_order_amount: form.min_order_amount || null,
    starts_at: form.starts_at ? form.starts_at.toISOString() : null,
    ends_at: form.ends_at ? form.ends_at.toISOString() : null,
    usage_limit: form.usage_limit,
    per_user_limit: form.per_user_limit,
    is_active: form.is_active,
  }
}

export const defaultValues: CouponForm = {
  code: '',
  name: null,
  type: 'percent',
  value: '10.00',
  max_discount_amount: null,
  min_order_amount: null,
  starts_at: null,
  ends_at: null,
  usage_limit: null,
  per_user_limit: null,
  is_active: true,
}

export function buildEditValues(currentRow?: Coupon): CouponForm {
  if (!currentRow) return { ...defaultValues }

  return {
    code: currentRow.code,
    name: currentRow.name,
    type: currentRow.type,
    value: currentRow.value.toString(),
    max_discount_amount: currentRow.max_discount_amount?.toString() ?? null,
    min_order_amount: currentRow.min_order_amount?.toString() ?? null,
    starts_at: currentRow.starts_at ? new Date(currentRow.starts_at) : null,
    ends_at: currentRow.ends_at ? new Date(currentRow.ends_at) : null,
    usage_limit: currentRow.usage_limit,
    per_user_limit: currentRow.per_user_limit,
    is_active: currentRow.is_active,
  }
}
