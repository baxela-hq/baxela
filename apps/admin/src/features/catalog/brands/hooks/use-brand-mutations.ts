import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createBrand,
  deleteBrand,
  updateBrand,
} from '../api/brands.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Brand, type BrandForm } from '../data/schema'

/**
 * Create or update a brand. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveBrand() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.BRAND)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: BrandForm }) =>
      id ? updateBrand(id, data) : createBrand(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('brand'),
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
 * Delete a single brand. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteBrand() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (brand: Brand) => deleteBrand(brand.id.toString()),
    onSuccess: (_result, brand) => {
      showSubmittedData(brand, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, brand) => {
      showSubmittedData(brand, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many brands. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteBrands() {
  return useMutation({
    mutationFn: async (brands: Brand[]) => {
      for (const brand of brands) {
        await deleteBrand(brand.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
