import apiClient, { getRequest, postRequest } from "@/shared/lib/api-client";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "catalog/admin/data";

// --- Module JSON export/import ---

type CatalogImportSectionCount = {
  entity: string;
  rows: number;
};

type CatalogImportRowError = {
  section: string;
  row: number;
  messages: string[];
};

type CatalogImportSectionSummary = {
  created: number;
  updated: number;
  skipped: number;
  failed: number;
};

export type CatalogImportPreview = {
  filename: string;
  format: string;
  module: string;
  version: number;
  exported_at: string | null;
  sections: CatalogImportSectionCount[];
  total_rows: number;
  row_cap: number;
  warnings: string[];
};

export type CatalogImportResult = {
  id: number;
  status: "completed" | "failed";
  strategy: "update" | "skip";
  dry_run: boolean;
  total_rows: number;
  created_count: number;
  updated_count: number;
  skipped_count: number;
  failed_count: number;
  duration_ms: number;
  sections: Record<string, CatalogImportSectionSummary>;
  warnings: string[];
  errors: CatalogImportRowError[];
};

export type CatalogImport = {
  id: number;
  user_id: number;
  media_id: number;
  filename: string;
  entity: string;
  status: "completed" | "failed";
  strategy: "update" | "skip";
  dry_run: boolean;
  total_rows: number;
  created_count: number;
  updated_count: number;
  skipped_count: number;
  failed_count: number;
  duration_ms: number;
  errors: CatalogImportRowError[] | null;
  summary: {
    sections: Record<string, CatalogImportSectionSummary>;
    warnings: string[] | null;
  } | null;
  created_at: string | null;
};

export type CatalogImportPayload = {
  media_id: number;
  on_duplicate: "update" | "skip";
  dry_run: boolean;
};

export async function previewCatalogImport(mediaId: number): Promise<CatalogImportPreview> {
  const response = await postRequest<SingleResponse<CatalogImportPreview>, { media_id: number }>(
    `${BASE_URL}/import/preview`,
    { media_id: mediaId },
  );
  return response.data as CatalogImportPreview;
}

export async function importCatalogData(payload: CatalogImportPayload): Promise<CatalogImportResult> {
  const response = await postRequest<SingleResponse<CatalogImportResult>, CatalogImportPayload>(
    `${BASE_URL}/import`,
    payload,
  );
  return response.data as CatalogImportResult;
}

export function fetchCatalogImports(queryParams = {}) {
  return getRequest<PaginatedResponse<CatalogImport>>(`${BASE_URL}/import`, queryParams);
}

/**
 * Download the whole-module JSON backup. The export lives behind auth, so
 * it is fetched as a blob through the shared axios client and handed to
 * the browser via a temporary object URL.
 */
export async function downloadCatalogDataExport(): Promise<void> {
  const response = await apiClient.get<Blob>(`${BASE_URL}/export`, {
    responseType: "blob",
  });
  const url = URL.createObjectURL(response.data);
  const link = document.createElement("a");
  link.href = url;
  link.download = `catalog-${new Date().toISOString().slice(0, 10)}.json`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
