import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { Category, CategoryForm } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "catalog/admin/categories";

export async function createCategory(request: CategoryForm): Promise<Category> {
  const { data } = await postRequest<SingleResponse<Category>, CategoryForm>(BASE_URL, request);
  return data as Category;
}

export function updateCategory(id: string, data: CategoryForm): Promise<Category> {
  return patchRequest<Category, CategoryForm>(`${BASE_URL}/${id}`, data);
}

export function fetchCategories(queryParams = {}){
  return getRequest<PaginatedResponse<Category>>(BASE_URL, queryParams);
}

export async function fetchOneCategory(id: string): Promise<Category>{
  const { data } = await getRequest<SingleResponse<Category>>(`${BASE_URL}/${id}`);
  return data as Category;
}

export function deleteCategory(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}


