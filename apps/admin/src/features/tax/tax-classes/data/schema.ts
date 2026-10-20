import { z } from 'zod'

export const taxClassSchema = z.object({
  id: z.number(),
  name: z.string(),
  is_default: z.boolean(),
  is_shipping: z.boolean(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type TaxClass = z.infer<typeof taxClassSchema>

export const formSchema = z.object({
  name: z.string().min(1, 'required').max(255),
  is_default: z.boolean(),
  is_shipping: z.boolean(),
})
export type TaxClassForm = z.infer<typeof formSchema>

export const defaultValues: TaxClassForm = {
  name: '',
  is_default: false,
  is_shipping: false,
}

export function buildEditValues(currentRow?: TaxClass): TaxClassForm {
  if (!currentRow) return { ...defaultValues }

  return {
    name: currentRow.name,
    is_default: currentRow.is_default,
    is_shipping: currentRow.is_shipping,
  }
}
