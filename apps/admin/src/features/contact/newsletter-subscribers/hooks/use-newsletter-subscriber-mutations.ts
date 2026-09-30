import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  deleteNewsletterSubscriber,
  updateNewsletterSubscriberStatus,
} from '../api/newsletter-subscribers.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type NewsletterSubscriber, type NewsletterSubscriberStatus } from '../data/schema'

/** Change a subscriber's status (quick actions in the row menu). */
export function useUpdateNewsletterSubscriberStatus() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tStatus } = useAppTranslation(Locales.NEWSLETTER_SUBSCRIBER)

  return useMutation({
    mutationFn: ({
      subscriber,
      status,
    }: {
      subscriber: NewsletterSubscriber
      status: NewsletterSubscriberStatus
    }) => updateNewsletterSubscriberStatus(subscriber.id.toString(), status),
    onSuccess: async (_result, { status }) => {
      toast.success(
        tMessage('success.default', {
          name: tLabel('subscriber'),
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

export function useDeleteNewsletterSubscriber() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: ({ subscriber }: { subscriber: NewsletterSubscriber }) =>
      deleteNewsletterSubscriber(subscriber.id.toString()),
    onSuccess: (_result, { subscriber }) => {
      showSubmittedData(subscriber, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, { subscriber }) => {
      showSubmittedData(subscriber, tMessage('error.general'))
    },
  })
}

/** Sequentially delete many subscribers. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback. */
export function useBulkDeleteNewsletterSubscribers() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (subscribers: NewsletterSubscriber[]) => {
      for (const subscriber of subscribers) {
        await deleteNewsletterSubscriber(subscriber.id.toString())
      }
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
