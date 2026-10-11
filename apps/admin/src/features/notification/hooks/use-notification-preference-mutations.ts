import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  updateNotificationPreference,
} from '../api/notification-preferences.api'
import { FeatureRoutes, Locales } from '../data/routes'
import type {
  NotificationPreference,
  NotificationPreferenceUpdate,
} from '../data/schema'

const preferencesQueryKey = () => [FeatureRoutes.CACHE_KEY, 'preferences']

/**
 * Flips one code/channel toggle. Optimistically mirrors the new state in
 * the preferences cache and rolls back (with the API's error toast) when
 * the server rejects it.
 */
export function useUpdateNotificationPreference() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (form: NotificationPreferenceUpdate) =>
      updateNotificationPreference(form),
    onMutate: async (form) => {
      await queryClient.cancelQueries({ queryKey: preferencesQueryKey() })

      const previous = queryClient.getQueryData(preferencesQueryKey())

      queryClient.setQueryData<
        { data: NotificationPreference[] } | undefined
      >(preferencesQueryKey(), (old) =>
        old
          ? {
              data: old.data.map((entry) =>
                entry.code === form.code
                  ? {
                      ...entry,
                      channels: entry.channels.map((channel) =>
                        channel.channel === form.channel
                          ? {
                              ...channel,
                              enabled: form.is_enabled,
                              is_default: false,
                            }
                          : channel
                      ),
                    }
                  : entry
              ),
            }
          : old
      )

      return { previous }
    },
    onError: (err: unknown, _form, context) => {
      if (context?.previous) {
        queryClient.setQueryData(preferencesQueryKey(), context.previous)
      }
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: preferencesQueryKey() })
    },
  })
}
