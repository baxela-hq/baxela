import apiClient, { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { Product, ProductPayload } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

export const BASE_URL = "catalog/admin/products";

export function fetchProducts(queryParams = {}){
  return getRequest<PaginatedResponse<Product>>(BASE_URL, queryParams);
}
export async function fetchOneProduct(id: string): Promise<Product>{
  const { data } = await getRequest<SingleResponse<Product>>(`${BASE_URL}/${id}`);
  return data as Product;
}
export function deleteProduct(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}
export async function createProduct(request: ProductPayload): Promise<Product> {
  const response = await postRequest<SingleResponse<Product>, ProductPayload>(BASE_URL, request);
  return response.data as Product;
}
export async function updateProduct(id: string, data: ProductPayload): Promise<Product> {
  const response = await patchRequest<SingleResponse<Product>, ProductPayload>(`${BASE_URL}/${id}`, data);
  return response.data as Product;
}

// --- CSV import ---

export type ProductImportRowError = {
  row: number;
  messages: string[];
};

export type ProductImportPreview = {
  filename: string;
  delimiter: string;
  headers: string[];
  rowCount: number;
  rowCap: number;
  sampleRows: string[][];
  suggestedMapping: Record<string, string | null>;
  availableFields: Record<string, string[]>;
  defaultLanguage: string;
  warnings: string[];
};

export type ProductImportResult = {
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
  errors: ProductImportRowError[];
};

export type ProductImport = {
  id: number;
  user_id: number;
  media_id: number;
  filename: string;
  status: "completed" | "failed";
  strategy: "update" | "skip";
  dry_run: boolean;
  total_rows: number;
  created_count: number;
  updated_count: number;
  skipped_count: number;
  failed_count: number;
  duration_ms: number;
  errors: ProductImportRowError[] | null;
  created_at: string | null;
};

export type ProductImportPayload = {
  media_id: number;
  mapping: Record<string, string | null>;
  on_duplicate: "update" | "skip";
  dry_run: boolean;
};

export async function previewProductImport(mediaId: number): Promise<ProductImportPreview> {
  const response = await postRequest<SingleResponse<ProductImportPreview>, { media_id: number }>(
    `${BASE_URL}/import/preview`,
    { media_id: mediaId },
  );
  return response.data as ProductImportPreview;
}

export async function importProducts(payload: ProductImportPayload): Promise<ProductImportResult> {
  const response = await postRequest<SingleResponse<ProductImportResult>, ProductImportPayload>(
    `${BASE_URL}/import`,
    payload,
  );
  return response.data as ProductImportResult;
}

export function fetchProductImports(queryParams = {}) {
  return getRequest<PaginatedResponse<ProductImport>>(`${BASE_URL}/import`, queryParams);
}

export async function fetchOneProductImport(id: string): Promise<ProductImport> {
  const { data } = await getRequest<SingleResponse<ProductImport>>(`${BASE_URL}/import/${id}`);
  return data as ProductImport;
}

/**
 * Download the example CSV. The template lives behind auth, so it is
 * fetched as a blob through the shared axios client and handed to the
 * browser via a temporary object URL.
 */
export async function downloadProductImportTemplate(): Promise<void> {
  const response = await apiClient.get<Blob>(`${BASE_URL}/import/template`, {
    responseType: "blob",
  });
  const url = URL.createObjectURL(response.data);
  const link = document.createElement("a");
  link.href = url;
  link.download = "product-import-template.csv";
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
