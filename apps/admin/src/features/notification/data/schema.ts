import { z } from 'zod'

export const notificationSchema = z.object({
  id: z.number(),
  code: z.string(),
  title: z.string(),
  body: z.string(),
  meta: z
    .object({
      order_code: z.string().optional(),
      reason: z.string().nullish(),
    })
    .nullish(),
  read_at: z.string().nullable(),
  created_at: z.string().nullable(),
})

export type Notification = z.infer<typeof notificationSchema>
