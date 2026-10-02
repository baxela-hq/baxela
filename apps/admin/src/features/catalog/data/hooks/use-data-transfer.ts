import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import type { PaginatedResponse } from '@/shared/types/common.types'
import {
  downloadCatalogDataExport,
  fetchCatalogImports,
  importCatalogData,
  previewCatalogImport,
  type CatalogImport,
  type CatalogImportPayload,
  type CatalogImportPreview,
  type CatalogImportResult,
} from '../api/data.api'
import { FeatureRoutes, Locales } from '../data/routes'

/** Validate an uploaded export file and list its sections. */
export function useCatalogImportPreview() {
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation<CatalogImportPreview, Error, number>({
    mutationFn: (mediaId: number) => previewCatalogImport(mediaId),
    onError: (error) =>
      error instanceof ApiError
        ? parseAndToastError(error)
        : toast.error(tMessage('error.default', { action: tAction('import') })),
  })
}

/** Run the import (or dry run) and refresh the history. */
export function useCatalogImport() {
  const queryClient = useQueryClient()
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation<CatalogImportResult, Error, CatalogImportPayload>({
    mutationFn: (payload: CatalogImportPayload) => importCatalogData(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY, 'imports'],
      })
    },
    onError: (error) =>
      error instanceof ApiError
        ? parseAndToastError(error)
        : toast.error(tMessage('error.default', { action: tAction('import') })),
  })
}

/** Import history, latest first. */
export function useCatalogImportsList() {
  return useQuery<PaginatedResponse<CatalogImport>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'imports'],
    queryFn: () => fetchCatalogImports(),
  })
}

/** Download the module JSON export with success/error toasts. */
export function useCatalogDataExport() {
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation<void, Error, void>({
    mutationFn: () => downloadCatalogDataExport(),
    onSuccess: () => toast.success(tMessage('success.default', { name: 'catalog', action: tAction('export') })),
    onError: () => toast.error(tMessage('error.general')),
  })
}
