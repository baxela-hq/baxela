import { z } from 'zod'

export const newsletterSubscriberStatusSchema = z.enum([
  'subscribed',
  'unsubscribed',
])
export type NewsletterSubscriberStatus = z.infer<typeof newsletterSubscriberStatusSchema>

export const newsletterSubscriberSchema = z.object({
  id: z.number(),
  email: z.string(),
  locale: z.string().nullable(),
  status: newsletterSubscriberStatusSchema,
  created_at: z.string(),
  updated_at: z.string(),
})
export type NewsletterSubscriber = z.infer<typeof newsletterSubscriberSchema>
