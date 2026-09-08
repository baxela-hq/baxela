import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { MenuLink, MenuLinkPayload } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "menu/admin/menus/$id/links";

export async function createMenuLink(menuId: string, request: MenuLinkPayload): Promise<MenuLink> {
  const { data } = await postRequest<SingleResponse<MenuLink>, MenuLinkPayload>(getUrl(menuId), request);
  return data as MenuLink;
}

export function updateMenuLink(menuId: string, id: string, data: MenuLinkPayload): Promise<MenuLink> {
  return patchRequest<MenuLink, MenuLinkPayload>(getUrl(menuId, id), data);
}

export function fetchMenuLinks(menuId: string, queryParams = {}){
  return getRequest<PaginatedResponse<MenuLink>>(getUrl(menuId), queryParams);
}

export async function fetchOneMenuLink(menuId: string, id: string): Promise<MenuLink>{
  const { data } = await getRequest<SingleResponse<MenuLink>>(getUrl(menuId, id));
  return data as MenuLink;
}

export function deleteMenuLink(menuId: string, id: string) {
  return deleteRequest(getUrl(menuId, id));
}

function getUrl(menuId: string, id?: string): string {
  const url = BASE_URL.replace("$id", menuId);
  return typeof id !== 'undefined' ? `${url}/${id}` : url;
}
