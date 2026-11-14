import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  archiveWebhookEndpoint,
  createWebhookEndpoint,
  regenerateWebhookSecret,
  retryWebhookDelivery,
  testWebhookEndpoint,
  updateWebhookEndpoint,
} from '../api/webhooks.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { useAppTranslation } from '@/hooks/useAppTranslation'

/**
 * Endpoint mutations (gap-C.2 UI). Create and regenerate return the
 * ONCE-ONLY signing secret — the components surface it in the
 * copy-now dialog and never persist it anywhere.
 */

export function useCreateWebhookEndpoint() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.WEBHOOK)

  return useMutation({
    mutationFn: createWebhookEndpoint,
    onSuccess: async () => {
      toast.success(tMessage('created'))
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error_general'))
    },
  })
}

export function useUpdateWebhookEndpoint() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.WEBHOOK)

  return useMutation({
    mutationFn: ({ id, ...input }: { id: number } & Parameters<typeof updateWebhookEndpoint>[1]) =>
      updateWebhookEndpoint(id, input),
    onSuccess: async () => {
      toast.success(tMessage('updated'))
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error_general'))
    },
  })
}

export function useArchiveWebhookEndpoint() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.WEBHOOK)

  return useMutation({
    mutationFn: archiveWebhookEndpoint,
    onSuccess: async () => {
      toast.success(tMessage('archived'))
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error_general'))
    },
  })
}

export function useRegenerateWebhookSecret() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.WEBHOOK)

  return useMutation({
    mutationFn: regenerateWebhookSecret,
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error_general'))
    },
  })
}

export function useTestWebhookEndpoint() {
  const { tMessage } = useAppTranslation(Locales.WEBHOOK)

  return useMutation({
    mutationFn: testWebhookEndpoint,
    onSuccess: () => {
      toast.success(tMessage('test_sent'))
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error_general'))
    },
  })
}

export function useRetryWebhookDelivery() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.WEBHOOK)

  return useMutation({
    mutationFn: retryWebhookDelivery,
    onSuccess: async () => {
      toast.success(tMessage('retry_queued'))
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY, 'deliveries'],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error_general'))
    },
  })
}
