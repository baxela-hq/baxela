import { z } from 'zod'

export const rateSchema = z.object({
  id: z.number(),
  method_id: z.number(),
  zone_id: z.number(),
  price: z.string(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Rate = z.infer<typeof rateSchema>

export const formSchema = z.object({
  method_id: z.number().min(1, 'required'),
  zone_id: z.number().min(1, 'required'),
  price: z.string().min(1, 'required'),
})
export type RateForm = z.infer<typeof formSchema>

export const defaultValues: RateForm = {
  method_id: 0,
  zone_id: 0,
  price: '0.00',
}

export function buildEditValues(currentRow?: Rate): RateForm {
  if (!currentRow) return { ...defaultValues }

  return {
    method_id: currentRow.method_id,
    zone_id: currentRow.zone_id,
    price: currentRow.price.toString(),
  }
}
