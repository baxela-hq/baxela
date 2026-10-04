import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  deleteTicket,
  replyToTicket,
  updateTicketStatus,
} from '../api/tickets.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Ticket, type TicketStatus } from '../data/schema'

/** Staff reply — marks the ticket answered and notifies the customer. */
export function useReplyToTicket() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.SUPPORT_TICKET)

  return useMutation({
    mutationFn: ({ id, body }: { id: number; body: string }) =>
      replyToTicket(id.toString(), body),
    onSuccess: async () => {
      toast.success(
        tMessage('success.default', {
          name: tLabel('ticket'),
          action: tLabel('replied').toLowerCase(),
        })
      )
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_SINGLE_KEY],
      })
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}

/** Change a ticket's status (row quick actions and the show page select). */
export function useUpdateTicketStatus() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tStatus } = useAppTranslation(Locales.SUPPORT_TICKET)

  return useMutation({
    mutationFn: ({
      ticket,
      status,
    }: {
      ticket: Ticket
      status: TicketStatus
    }) => updateTicketStatus(ticket.id.toString(), status),
    onSuccess: async (_result, { status }) => {
      toast.success(
        tMessage('success.default', {
          name: tLabel('ticket'),
          action: tStatus(`status.${status}`).toLowerCase(),
        })
      )
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_SINGLE_KEY],
      })
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}

export function useDeleteTicket() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: ({ ticket }: { ticket: Ticket }) =>
      deleteTicket(ticket.id.toString()),
    onSuccess: (_result, { ticket }) => {
      showSubmittedData(ticket, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, { ticket }) => {
      showSubmittedData(ticket, tMessage('error.general'))
    },
  })
}

/** Sequentially delete many tickets. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback. */
export function useBulkDeleteTickets() {
  return useMutation({
    mutationFn: async (tickets: Ticket[]) => {
      for (const ticket of tickets) {
        await deleteTicket(ticket.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
