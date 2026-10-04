import { createFileRoute } from '@tanstack/react-router'
import { TicketShow } from '@/features/support/tickets/show'

export const Route = createFileRoute('/_authenticated/support/tickets/$id/show')({
  component: TicketShow,
})
