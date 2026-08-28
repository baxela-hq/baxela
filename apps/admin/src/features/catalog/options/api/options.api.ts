import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { Option, OptionForm } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "catalog/admin/options";

export async function createOption(request: OptionForm): Promise<Option> {
  const { data } = await postRequest<SingleResponse<Option>, OptionForm>(BASE_URL, request);
  return data as Option;
}

export function updateOption(id: string, data: OptionForm): Promise<Option> {
  return patchRequest<Option, OptionForm>(`${BASE_URL}/${id}`, data);
}

export function fetchOptions(queryParams = {}){
  return getRequest<PaginatedResponse<Option>>(BASE_URL, queryParams);
}

export async function fetchOneOption(id: string): Promise<Option>{
  const { data } = await getRequest<SingleResponse<Option>>(`${BASE_URL}/${id}`);
  return data as Option;
}

export function deleteOption(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}


