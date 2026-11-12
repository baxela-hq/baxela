import { z } from 'zod'

export const sessionSchema = z.object({
  id: z.number(),
  /** Device name the token was issued for. */
  name: z.string(),
  abilities: z.array(z.string()),
  created_at: z.string().nullable(),
  last_used_at: z.string().nullable(),
  expires_at: z.string().nullable(),
  is_current: z.boolean(),
})

export type Session = z.infer<typeof sessionSchema>
