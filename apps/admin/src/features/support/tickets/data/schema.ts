import { z } from 'zod'

export const ticketStatusSchema = z.enum(['open', 'answered', 'closed'])
export type TicketStatus = z.infer<typeof ticketStatusSchema>

export const ticketSenderSchema = z.enum(['customer', 'admin'])

export const ticketSchema = z.object({
  id: z.number(),
  user_id: z.number(),
  customer_name: z.string().nullable(),
  customer_email: z.string().nullable(),
  subject: z.string(),
  status: ticketStatusSchema,
  order_code: z.string().nullable(),
  last_message_at: z.string().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
  // present on the show endpoint only
  messages: z
    .array(
      z.object({
        id: z.number(),
        ticket_id: z.number().optional(),
        user_id: z.number().optional(),
        sender: ticketSenderSchema,
        body: z.string(),
        created_at: z.string(),
      })
    )
    .optional(),
})
export type Ticket = z.infer<typeof ticketSchema>

export type TicketMessage = NonNullable<Ticket['messages']>[number]

export const replyFormSchema = z.object({
  body: z.string().min(1).max(5000),
})
export type TicketReplyForm = z.infer<typeof replyFormSchema>
