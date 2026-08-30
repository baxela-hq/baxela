import { z } from 'zod'

export const zoneSchema = z.object({
  id: z.number(),
  name: z.string(),
  is_active: z.boolean(),
  position: z.string(),
  country_codes: z.array(z.string()),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Zone = z.infer<typeof zoneSchema>

export const formSchema = z.object({
  name: z.string().min(1, 'required').max(255),
  is_active: z.boolean(),
  position: z.string().max(255),
  country_codes: z.array(z.string()),
})
export type ZoneForm = z.infer<typeof formSchema>

export const defaultValues: ZoneForm = {
  name: '',
  is_active: true,
  position: '1',
  country_codes: [],
}

export function buildEditValues(currentRow?: Zone): ZoneForm {
  if (!currentRow) return { ...defaultValues }

  return {
    name: currentRow.name,
    is_active: currentRow.is_active,
    position: currentRow.position.toString(),
    country_codes: [...currentRow.country_codes],
  }
}
