import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { createCurrency, updateCurrency } from '../api/currencies.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type CurrencyForm } from '../data/schema'

/**
 * Patch a currency — the edit drawer sends the form fields, the row
 * switch sends only `{ is_active }`. Invalidates the shared `['currencies']`
 * list so the settings page dropdown and this table stay in sync.
 */
export function useUpdateCurrency() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.CURRENCY)

  return useMutation({
    mutationFn: ({
      id,
      data,
    }: {
      id: number
      data: Partial<CurrencyForm> & { is_active?: boolean }
    }) => updateCurrency(id, data),
    onSuccess: async () => {
      toast.success(
        tMessage('success.record.updated', { name: tLabel('currency') })
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

/** Create a currency — the add drawer's submit path. */
export function useCreateCurrency() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.CURRENCY)

  return useMutation({
    mutationFn: (data: Partial<CurrencyForm>) => createCurrency(data),
    onSuccess: async () => {
      toast.success(
        tMessage('success.record.created', { name: tLabel('currency') })
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
