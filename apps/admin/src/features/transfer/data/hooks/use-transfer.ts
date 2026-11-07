import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  downloadTransferExport,
  fetchTransferCapabilities,
  fetchTransferImports,
  importTransferData,
  previewTransferImport,
  type TransferCapability,
  type TransferImport,
  type TransferImportPayload,
  type TransferImportPreview,
  type TransferImportResult,
} from '../api/transfer.api'
import { FeatureRoutes, Locales } from '../data/routes'

/**
 * The coverage matrix: every enabled module with the sections it
 * exposes for export/import and its own data-endpoint URLs — null for
 * modules that have not opted into the standard yet.
 */
export function useTransferCapabilities() {
  return useQuery<TransferCapability[]>({
    queryKey: [FeatureRoutes.CACHE_KEY, FeatureRoutes.CAPABILITIES_KEY],
    queryFn: fetchTransferCapabilities,
  })
}

/** Validate an uploaded transfer file on the target module's endpoint. */
export function useTransferImportPreview() {
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation<
    TransferImportPreview,
    Error,
    { file: File; previewUrl: string }
  >({
    mutationFn: ({ file, previewUrl }) =>
      previewTransferImport(file, previewUrl),
    onError: (error) =>
      error instanceof ApiError
        ? parseAndToastError(error)
        : toast.error(tMessage('error.default', { action: tAction('import') })),
  })
}

/** Run the import on the module's own endpoint and refresh the history. */
export function useTransferImport() {
  const queryClient = useQueryClient()
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation<TransferImportResult, Error, TransferImportPayload>({
    mutationFn: (payload: TransferImportPayload) => importTransferData(payload),
    onSuccess: async (result) => {
      toast.success(
        tMessage('success.default', {
          name: result.module,
          action: tAction('import'),
        })
      )
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY, FeatureRoutes.IMPORTS_KEY],
      })
    },
    onError: (error) =>
      error instanceof ApiError
        ? parseAndToastError(error)
        : toast.error(tMessage('error.default', { action: tAction('import') })),
  })
}

/** Import history across all modules, latest first (central audit plane). */
export function useTransferImportsList() {
  return useQuery<PaginatedResponse<TransferImport>>({
    queryKey: [FeatureRoutes.CACHE_KEY, FeatureRoutes.IMPORTS_KEY],
    queryFn: () => fetchTransferImports(),
  })
}

/** Download a module's transfer file from its own export endpoint. */
export function useTransferExport() {
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation<void, Error, { module: string; exportUrl: string }>({
    mutationFn: ({ exportUrl }) => downloadTransferExport(exportUrl),
    onSuccess: (_result, { module }) =>
      toast.success(
        tMessage('success.default', { name: module, action: tAction('export') })
      ),
    onError: (error) =>
      error instanceof ApiError
        ? parseAndToastError(error)
        : toast.error(tMessage('error.general')),
  })
}
