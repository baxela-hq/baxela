import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { Brand, BrandForm } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "catalog/admin/brands";

export async function createBrand(request: BrandForm): Promise<Brand> {
  const { data } = await postRequest<SingleResponse<Brand>, BrandForm>(BASE_URL, request);
  return data as Brand;
}

export function updateBrand(id: string, data: BrandForm): Promise<Brand> {
  return patchRequest<Brand, BrandForm>(`${BASE_URL}/${id}`, data);
}

export function fetchBrands(queryParams = {}){
  return getRequest<PaginatedResponse<Brand>>(BASE_URL, queryParams);
}

export async function fetchOneBrand(id: string): Promise<Brand>{
  const { data } = await getRequest<SingleResponse<Brand>>(`${BASE_URL}/${id}`);
  return data as Brand;
}

export function deleteBrand(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}
