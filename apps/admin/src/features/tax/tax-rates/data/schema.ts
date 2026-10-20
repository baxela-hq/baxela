import { z } from 'zod'

export const taxRateSchema = z.object({
  id: z.number(),
  name: z.string(),
  country_code: z.string(),
  tax_class_id: z.number(),
  rate: z.string(),
  is_active: z.boolean(),
  tax_class: z
    .object({
      id: z.number(),
      name: z.string(),
    })
    .nullable()
    .optional(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type TaxRate = z.infer<typeof taxRateSchema>

export const formSchema = z.object({
  name: z.string().min(1, 'required').max(255),
  country_code: z.string().min(1, 'required').max(2),
  tax_class_id: z.number({ message: 'required' }).int().positive(),
  // percentage 0–100 with at most 4 fraction digits, matching decimal(7,4)
  rate: z
    .string()
    .min(1, 'required')
    .regex(/^\d{1,3}(\.\d{1,4})?$/, 'invalid'),
  is_active: z.boolean(),
})
export type TaxRateForm = z.infer<typeof formSchema>

export const defaultValues: TaxRateForm = {
  name: '',
  country_code: '',
  tax_class_id: 0,
  rate: '',
  is_active: true,
}

export function buildEditValues(currentRow?: TaxRate): TaxRateForm {
  if (!currentRow) return { ...defaultValues }

  return {
    name: currentRow.name,
    country_code: currentRow.country_code,
    tax_class_id: currentRow.tax_class_id,
    rate: currentRow.rate.replace(/0+$/, '').replace(/\.$/, '') || '0',
    is_active: currentRow.is_active,
  }
}
