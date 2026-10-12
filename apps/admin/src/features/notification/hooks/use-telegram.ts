import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  fetchTelegramBotInfo,
  fetchTelegramSubscription,
  linkTelegram,
  sendTelegramTest,
  unlinkTelegram,
} from '../api/telegram.api'
import { FeatureRoutes, Locales } from '../data/routes'

const telegramQueryKey = () => [FeatureRoutes.CACHE_KEY, 'telegram']

/**
 * Telegram linking state for the signed-in account: bot identity (for the
 * deep link), the linked chat, and the link/unlink/test flows. Codes and
 * chat ids are single-credential — one of the two is always null.
 */
export function useTelegramNotifications() {
  const queryClient = useQueryClient()
  const { t } = useAppTranslation(Locales.NOTIFICATION)
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const { data: botInfoData, isLoading: botInfoLoading } = useQuery({
    queryKey: [...telegramQueryKey(), 'bot-info'],
    queryFn: () => fetchTelegramBotInfo(),
  })

  const { data: subscriptionData, isLoading: subscriptionLoading } = useQuery(
    {
      queryKey: [...telegramQueryKey(), 'subscription'],
      queryFn: () => fetchTelegramSubscription(),
    }
  )

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: telegramQueryKey() })

  const onError = (err: unknown) => {
    if (err instanceof ApiError) parseAndToastError(err)
    else toast.error(tMessage('error.general'))
  }

  const link = useMutation({
    mutationFn: linkTelegram,
    onSuccess: async () => {
      toast.success(t('telegram.messages.linked'))
      await invalidate()
    },
    onError,
  })

  const unlink = useMutation({
    mutationFn: unlinkTelegram,
    onSuccess: async () => {
      toast.success(t('telegram.messages.unlinked'))
      await invalidate()
    },
    onError,
  })

  const test = useMutation({
    mutationFn: sendTelegramTest,
    onSuccess: async () => {
      toast.success(t('telegram.messages.test_sent'))
    },
    onError,
  })

  return {
    botInfo: botInfoData?.data ?? null,
    botInfoLoading,
    subscription: subscriptionData?.data ?? null,
    subscriptionLoading,
    link,
    unlink,
    test,
  }
}
