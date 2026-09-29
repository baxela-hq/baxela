import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import type { PaginatedResponse } from '@/shared/types/common.types'
import {
  downloadProductImportTemplate,
  fetchProductImports,
  importProducts,
  previewProductImport,
  type ProductImport,
  type ProductImportPayload,
  type ProductImportPreview,
  type ProductImportResult,
} from '../api/products.api'
import { FeatureRoutes, Locales } from '../data/routes'

/** Parse a stored CSV and return headers, sample rows and a suggested mapping. */
export function useProductImportPreview() {
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation<ProductImportPreview, Error, number>({
    mutationFn: (mediaId: number) => previewProductImport(mediaId),
    onError: (error) =>
      error instanceof ApiError
        ? parseAndToastError(error)
        : toast.error(tMessage('error.default', { action: tAction('import') })),
  })
}

/** Run the import (or dry run) and refresh both history and products. */
export function useProductImport() {
  const queryClient = useQueryClient()
  const { tLabel } = useAppTranslation(Locales.PRODUCT)
  const { tAction: commonAction, tMessage: commonMessage } = useAppTranslation(
    Locales.SHARED_COMMON,
  )

  return useMutation<ProductImportResult, Error, ProductImportPayload>({
    mutationFn: (payload: ProductImportPayload) => importProducts(payload),
    onSuccess: async (result) => {
      if (result.created_count + result.updated_count > 0) {
        toast.success(
          commonMessage('success.default', {
            name: tLabel('products'),
            action: commonAction('import'),
          }),
        )
        await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
      }
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY, 'imports'],
      })
    },
    onError: (error) =>
      error instanceof ApiError
        ? parseAndToastError(error)
        : toast.error(commonMessage('error.default', { action: commonAction('import') })),
  })
}

/** Import history, latest first. */
export function useProductImportsList() {
  return useQuery<PaginatedResponse<ProductImport>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'imports'],
    queryFn: () => fetchProductImports(),
  })
}

/** Download the example CSV (with toasts handled by the caller via toast.promise). */
export function useProductImportTemplate() {
  return useMutation<void, Error, void>({
    mutationFn: () => downloadProductImportTemplate(),
  })
}
