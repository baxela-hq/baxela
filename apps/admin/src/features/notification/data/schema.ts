import { z } from 'zod'

export const notificationSchema = z.object({
  id: z.number(),
  code: z.string(),
  title: z.string(),
  body: z.string(),
  meta: z
    .object({
      order_code: z.string().optional(),
      order_id: z.number().nullish(),
      ticket_id: z.number().nullish(),
      reason: z.string().nullish(),
      post_id: z.number().nullish(),
      post_comment_id: z.number().nullish(),
    })
    .nullish(),
  read_at: z.string().nullable(),
  created_at: z.string().nullable(),
})

export type Notification = z.infer<typeof notificationSchema>
