import apiClient, { getRequest, postRequest } from '@/shared/lib/api-client'
import {
  type PaginatedResponse,
  type SingleResponse,
} from '@/shared/types/common.types'

const BASE_URL = 'transfer/admin'

// --- Capabilities matrix ---

export type TransferSection = {
  key: string
  exportable: boolean
  importable: boolean
}

/** The module's own data-endpoint URLs, relative to the API root. */
export type TransferEndpoints = {
  export: string
  import_preview: string
  import: string
  imports: string
}

export type TransferCapability = {
  module: string
  supported: boolean
  endpoints: TransferEndpoints | null
  sections: TransferSection[]
}

export function fetchTransferCapabilities(): Promise<TransferCapability[]> {
  return getRequest<SingleResponse<TransferCapability[]>>(
    `${BASE_URL}/capabilities`
  ).then((response) => response.data)
}

// --- Import preview / run / history ---

type TransferSectionCount = {
  entity: string
  rows: number
}

type TransferSectionSummary = {
  created: number
  updated: number
  skipped: number
  failed: number
}

export type TransferImportPreview = {
  filename: string
  format: string
  module: string
  version: number
  exported_at: string | null
  sections: TransferSectionCount[]
  total_rows: number
  row_cap: number
  warnings: string[]
}

export type TransferImportResult = {
  id: number
  module: string
  status: 'completed' | 'failed'
  strategy: 'update' | 'skip'
  dry_run: boolean
  total_rows: number
  created_count: number
  updated_count: number
  skipped_count: number
  failed_count: number
  duration_ms: number
  sections: Record<string, TransferSectionSummary>
  warnings: string[]
  /** Per-row failure strings, "{section}[{n}]: reason" — owned by the module implementations. */
  errors: string[]
}

export type TransferImport = {
  id: number
  user_id: number | null
  module: string
  filename: string
  status: 'completed' | 'failed'
  strategy: 'update' | 'skip'
  dry_run: boolean
  total_rows: number
  created_count: number
  updated_count: number
  skipped_count: number
  failed_count: number
  duration_ms: number
  errors: string[] | null
  summary: {
    sections: Record<string, TransferSectionSummary>
    warnings: string[] | null
  } | null
  created_at: string | null
}

export type TransferImportPayload = {
  file: File
  on_duplicate: 'update' | 'skip'
  dry_run: boolean
  /** The module's own import endpoint, from its published capabilities. */
  importUrl: string
}

/** Structure check of an uploaded transfer file (multipart, JSON only). */
export async function previewTransferImport(
  file: File,
  previewUrl: string
): Promise<TransferImportPreview> {
  const formData = new FormData()
  formData.append('file', file)

  // The shared client defaults to application/json — the multipart
  // boundary header must be set explicitly (mirrors uploadMedia).
  const { data } = await postRequest<
    SingleResponse<TransferImportPreview>,
    FormData
  >(previewUrl, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data as TransferImportPreview
}

/** Replay an uploaded transfer file (dry runs validate without writing). */
export async function importTransferData(
  payload: TransferImportPayload
): Promise<TransferImportResult> {
  const formData = new FormData()
  formData.append('file', payload.file)
  formData.append('on_duplicate', payload.on_duplicate)
  formData.append('dry_run', payload.dry_run ? '1' : '0')

  const { data } = await postRequest<
    SingleResponse<TransferImportResult>,
    FormData
  >(payload.importUrl, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data as TransferImportResult
}

/** Cross-module run history — the central audit plane. */
export function fetchTransferImports(queryParams = {}) {
  return getRequest<PaginatedResponse<TransferImport>>(
    `${BASE_URL}/imports`,
    queryParams
  )
}

/**
 * Download a module's transfer file from its own export endpoint. The
 * export lives behind auth, so it is fetched as a blob through the
 * shared axios client; the filename comes from the Content-Disposition
 * header the backend sets.
 */
export async function downloadTransferExport(exportUrl: string): Promise<void> {
  const response = await apiClient.get<Blob>(exportUrl, {
    responseType: 'blob',
  })

  const disposition =
    (response.headers['content-disposition'] as string | undefined) ?? ''
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)
  const filename = match?.[1] ?? 'transfer.json'

  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
