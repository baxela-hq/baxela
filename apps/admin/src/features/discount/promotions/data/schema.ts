import { z } from 'zod'

export const promotionTypeEnum = z.enum(['percent', 'fixed'])
export type PromotionType = z.infer<typeof promotionTypeEnum>

export const promotionScopeEnum = z.enum(['all', 'specific'])
export type PromotionScope = z.infer<typeof promotionScopeEnum>

export const promotionSchema = z.object({
  id: z.number(),
  name: z.string(),
  scope: promotionScopeEnum,
  type: promotionTypeEnum,
  // percent: 20.00 means 20% off each unit; fixed: major-unit amount off each unit
  value: z.string(),
  starts_at: z.string().nullable(),
  ends_at: z.string().nullable(),
  priority: z.number(),
  is_active: z.boolean(),
  product_ids: z.array(z.number()),
  category_ids: z.array(z.number()),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Promotion = z.infer<typeof promotionSchema>

export const formSchema = z
  .object({
    name: z.string().min(1, 'required'),
    scope: promotionScopeEnum,
    type: promotionTypeEnum,
    value: z.string().min(1, 'required'),
    priority: z.number(),
    starts_at: z.date().nullable(),
    ends_at: z.date().nullable(),
    is_active: z.boolean(),
    product_ids: z.array(z.number()),
    category_ids: z.array(z.number()),
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
    // An empty specific scope must never look like a store-wide promotion
    if (
      values.scope === 'specific' &&
      values.product_ids.length === 0 &&
      values.category_ids.length === 0
    ) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['scope'],
      })
    }
  })
export type PromotionForm = z.infer<typeof formSchema>

/** Wire format: dates as ISO-8601 UTC strings, scope selections as id arrays. */
export type PromotionPayload = {
  name: string
  scope: PromotionScope
  type: PromotionType
  value: string
  starts_at: string | null
  ends_at: string | null
  priority: number
  is_active: boolean
  product_ids: number[]
  category_ids: number[]
}

export function toPayload(form: PromotionForm): PromotionPayload {
  return {
    name: form.name.trim(),
    scope: form.scope,
    type: form.type,
    value: form.value,
    starts_at: form.starts_at ? form.starts_at.toISOString() : null,
    ends_at: form.ends_at ? form.ends_at.toISOString() : null,
    priority: form.priority,
    is_active: form.is_active,
    product_ids: form.scope === 'specific' ? form.product_ids : [],
    category_ids: form.scope === 'specific' ? form.category_ids : [],
  }
}

export const defaultValues: PromotionForm = {
  name: '',
  scope: 'specific',
  type: 'percent',
  value: '20.00',
  priority: 0,
  starts_at: null,
  ends_at: null,
  is_active: true,
  product_ids: [],
  category_ids: [],
}

export function buildEditValues(currentRow?: Promotion): PromotionForm {
  if (!currentRow) return { ...defaultValues }

  return {
    name: currentRow.name,
    scope: currentRow.scope,
    type: currentRow.type,
    value: currentRow.value.toString(),
    priority: currentRow.priority,
    starts_at: currentRow.starts_at ? new Date(currentRow.starts_at) : null,
    ends_at: currentRow.ends_at ? new Date(currentRow.ends_at) : null,
    is_active: currentRow.is_active,
    product_ids: currentRow.product_ids,
    category_ids: currentRow.category_ids,
  }
}
