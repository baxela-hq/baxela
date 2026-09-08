import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  deleteContactMessage,
  updateContactMessageStatus,
} from '../api/contact-messages.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type ContactMessage, type ContactMessageStatus } from '../data/schema'

/** Change a message's status (quick actions and the view drawer select). */
export function useUpdateContactMessageStatus() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tStatus } = useAppTranslation(Locales.CONTACT_MESSAGE)

  return useMutation({
    mutationFn: ({
      message,
      status,
    }: {
      message: ContactMessage
      status: ContactMessageStatus
    }) => updateContactMessageStatus(message.id.toString(), status),
    onSuccess: async (_result, { status }) => {
      toast.success(
        tMessage('success.default', {
          name: tLabel('message'),
          action: tStatus(`status.${status}`).toLowerCase(),
        })
      )
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

export function useDeleteContactMessage() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: ({ message }: { message: ContactMessage }) =>
      deleteContactMessage(message.id.toString()),
    onSuccess: (_result, { message }) => {
      showSubmittedData(message, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, { message }) => {
      showSubmittedData(message, tMessage('error.general'))
    },
  })
}

/** Sequentially delete many messages. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback. */
export function useBulkDeleteContactMessages() {
  return useMutation({
    mutationFn: async (messages: ContactMessage[]) => {
      for (const message of messages) {
        await deleteContactMessage(message.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
