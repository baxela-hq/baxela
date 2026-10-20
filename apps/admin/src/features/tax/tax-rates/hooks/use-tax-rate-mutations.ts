import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createTaxRate,
  deleteTaxRate,
  updateTaxRate,
} from '../api/tax-rates.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type TaxRate, type TaxRateForm } from '../data/schema'

/**
 * Create or update a tax rate. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveTaxRate() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.TAX_RATE)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: TaxRateForm }) =>
      id ? updateTaxRate(id, data) : createTaxRate(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('tax_rate'),
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

/**
 * Delete a single tax rate. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteTaxRate() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (taxRate: TaxRate) => deleteTaxRate(taxRate.id.toString()),
    onSuccess: (_result, taxRate) => {
      showSubmittedData(taxRate, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, taxRate) => {
      showSubmittedData(taxRate, tMessage('error.general'))
    },
  })
}

/** Sequentially delete many tax rates. */
export function useBulkDeleteTaxRates() {
  return useMutation({
    mutationFn: async (taxRates: TaxRate[]) => {
      for (const taxRate of taxRates) {
        await deleteTaxRate(taxRate.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
