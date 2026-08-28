import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
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
