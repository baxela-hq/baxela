import { z } from 'zod'

export const contactMessageStatusSchema = z.enum([
  'unread',
  'read',
  'replied',
  'archived',
])
export type ContactMessageStatus = z.infer<typeof contactMessageStatusSchema>

export const contactMessageSchema = z.object({
  id: z.number(),
  name: z.string(),
  email: z.string(),
  phone: z.string().nullable(),
  subject: z.string(),
  content: z.string(),
  status: contactMessageStatusSchema,
  ip_address: z.string().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type ContactMessage = z.infer<typeof contactMessageSchema>
