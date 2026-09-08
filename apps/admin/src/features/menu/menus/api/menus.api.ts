import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { Menu, MenuForm } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "menu/admin/menus";

export async function createMenu(request: MenuForm): Promise<Menu> {
  const { data } = await postRequest<SingleResponse<Menu>, MenuForm>(BASE_URL, request);
  return data as Menu;
}

export function updateMenu(id: string, data: MenuForm): Promise<Menu> {
  return patchRequest<Menu, MenuForm>(`${BASE_URL}/${id}`, data);
}

export function fetchMenus(queryParams = {}){
  return getRequest<PaginatedResponse<Menu>>(BASE_URL, queryParams);
}

export async function fetchOneMenu(id: string): Promise<Menu>{
  const { data } = await getRequest<SingleResponse<Menu>>(`${BASE_URL}/${id}`);
  return data as Menu;
}

export function deleteMenu(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}
