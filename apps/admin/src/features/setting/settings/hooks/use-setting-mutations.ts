import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { StorageUtility, StorageKeys } from '@/shared/lib/storage-utility'
import { parseAndToastError } from '@/shared/lib/utils'
import type { Currency, Language } from '@/shared/types/locale.types'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { updateSettings } from '../api/settings.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { buildSettingsRequest, type SettingsForm } from '../data/schema'

/**
 * Persist the settings form. The request mapping (form values + languages →
 * API payload), cache invalidation, the default language/currency snapshot
 * refresh and the success toast live here.
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
      currencies: Currency[]
    }) => updateSettings(buildSettingsRequest(values, languages)),
    onSuccess: async (_result, { values, languages, currencies }) => {
      // getDefaultCurrency()/getDefaultLanguage() read this snapshot, so it
      // must track the just-saved defaults for the rest of the session.
      const language = languages.find(
        (item) => String(item.id) === values.language_id
      )
      const currency = currencies.find(
        (item) => String(item.id) === values.currency_id
      )
      if (language)
        StorageUtility.setItem(StorageKeys.DEFAULT_LANGUAGE, language)
      if (currency)
        StorageUtility.setItem(StorageKeys.DEFAULT_CURRENCY, currency)

      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
      await queryClient.invalidateQueries({ queryKey: ['languages'] })
      await queryClient.invalidateQueries({ queryKey: ['currencies'] })
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
