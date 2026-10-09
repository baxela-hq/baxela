import {
  deleteRequest,
  getRequest,
  patchRequest,
  postRequest,
} from '@/shared/lib/api-client'
import {
  type PaginatedResponse,
  type SingleResponse,
} from '@/shared/types/common.types'
import { type Ticket, type TicketMessage, type TicketStatus } from '../data/schema'

const BASE_URL = 'support/admin/tickets'

export function fetchTickets(queryParams = {}) {
  return getRequest<PaginatedResponse<Ticket>>(BASE_URL, queryParams)
}

export async function fetchOneTicket(id: string): Promise<Ticket> {
  const { data } = await getRequest<SingleResponse<Ticket>>(
    `${BASE_URL}/${id}`
  )
  return data as Ticket
}

/** Staff reply; the backend marks the ticket as answered. */
export function replyToTicket(id: string, body: string) {
  return postRequest<TicketMessage, { body: string }>(
    `${BASE_URL}/${id}/messages`,
    { body }
  )
}

export function updateTicketStatus(id: string, status: TicketStatus) {
  return patchRequest<Ticket, { status: TicketStatus }>(
    `${BASE_URL}/${id}/status`,
    { status }
  )
}

export function deleteTicket(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
