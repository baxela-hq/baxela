import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createTaxClass,
  deleteTaxClass,
  updateTaxClass,
} from '../api/tax-classes.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type TaxClass, type TaxClassForm } from '../data/schema'

/**
 * Create or update a tax class. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveTaxClass() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.TAX_CLASS)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: TaxClassForm }) =>
      id ? updateTaxClass(id, data) : createTaxClass(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('tax_class'),
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
 * Delete a single tax class. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteTaxClass() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (taxClass: TaxClass) => deleteTaxClass(taxClass.id.toString()),
    onSuccess: (_result, taxClass) => {
      showSubmittedData(taxClass, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, taxClass) => {
      showSubmittedData(taxClass, tMessage('error.general'))
    },
  })
}

/** Sequentially delete many tax classes. */
export function useBulkDeleteTaxClasses() {
  return useMutation({
    mutationFn: async (taxClasses: TaxClass[]) => {
      for (const taxClass of taxClasses) {
        await deleteTaxClass(taxClass.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
