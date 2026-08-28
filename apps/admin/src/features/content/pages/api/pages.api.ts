import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { Page, PageForm } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

export const BASE_URL = "content/admin/pages";

export function fetchPages(queryParams = {}){
  return getRequest<PaginatedResponse<Page>>(BASE_URL, queryParams);
}

export async function fetchOnePage(id: string): Promise<Page>{
  const { data } = await getRequest<SingleResponse<Page>>(`${BASE_URL}/${id}`);
  return data as Page;
}

export function deletePage(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}

export async function createPage(request: PageForm): Promise<Page> {
  const { data } = await postRequest<SingleResponse<Page>, PageForm>(BASE_URL, request);
  return data as Page;
}

export function updatePage(id: string, data: PageForm): Promise<Page> {
  return patchRequest<Page, PageForm>(`${BASE_URL}/${id}`, data);
}
