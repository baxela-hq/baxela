import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { updateSettings } from '../api/settings.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { buildSettingsRequest, type SettingsForm } from '../data/schema'
import type { Language } from '@/shared/types/locale.types'

/**
 * Persist the settings form. The request mapping (form values + languages →
 * API payload), cache invalidation and success toast live here.
 */
export function useUpdateSettings() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.SETTING)

  return useMutation({
    mutationFn: ({
      values,
      languages,
    }: {
      values: SettingsForm
      languages: Language[]
    }) => updateSettings(buildSettingsRequest(values, languages)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
      toast.success(
        tMessage('success.record.updated', { name: tLabel('setting') })
      )
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}
