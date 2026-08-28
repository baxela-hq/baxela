import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { OptionValue, OptionValueForm } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "catalog/admin/options/$id/values";

export async function createOptionValue(optionId: string, request: OptionValueForm): Promise<OptionValue> {
  const { data } = await postRequest<SingleResponse<OptionValue>, OptionValueForm>(getUrl(optionId), request);
  return data as OptionValue;
}

export function updateOptionValue(optionId: string, id: string, data: OptionValueForm): Promise<OptionValue> {
  return patchRequest<OptionValue, OptionValueForm>(getUrl(optionId, id), data);
}

export function fetchOptionValues(optionId: string, queryParams = {}){
  return getRequest<PaginatedResponse<OptionValue>>(getUrl(optionId), queryParams);
}

export async function fetchOneOptionValue(optionId: string, id: string): Promise<OptionValue>{
  const { data } = await getRequest<SingleResponse<OptionValue>>(getUrl(optionId, id));
  return data as OptionValue;
}

export function deleteOptionValue(optionId: string, id: string) {
  return deleteRequest(getUrl(optionId, id));
}

function getUrl(optionId: string, id?: string): string {
  const url = BASE_URL.replace("$id", optionId);
  return typeof id !== 'undefined' ? `${url}/${id}` : url;
}


