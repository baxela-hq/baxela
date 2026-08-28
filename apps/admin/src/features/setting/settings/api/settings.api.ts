import { getRequest, patchRequest } from "@/shared/lib/api-client";
import type { Setting, SettingRequest } from "../data/schema";
import { type AllResponse } from "@/shared/types/common.types";

const BASE_URL = "setting/admin/settings";

export async function updateSettings(request: SettingRequest[]): Promise<Setting[]> {
  const { data } = await patchRequest<AllResponse<Setting>, SettingRequest[]>(BASE_URL, request);
  return data as Setting[];
}

export async function fetchSettings(queryParams = {}): Promise<Setting[]> {
  const { data } = await  getRequest<AllResponse<Setting>>(BASE_URL, queryParams);
  return data as Setting[];
}


