import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { User, UserForm } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "auth/admin/users";

export async function createUser(request: UserForm): Promise<User> {
  const { data } = await postRequest<SingleResponse<User>, UserForm>(BASE_URL, request);
  return data as User;
}

export function updateUser(id: string, data: UserForm): Promise<User> {
  return patchRequest<User, UserForm>(`${BASE_URL}/${id}`, data);
}

export function fetchUsers(queryParams = {}){
  return getRequest<PaginatedResponse<User>>(BASE_URL, queryParams);
}

export async function fetchOneUser(id: string): Promise<User>{
  const { data } = await getRequest<SingleResponse<User>>(`${BASE_URL}/${id}`);
  return data as User;
}

export function deleteUser(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}


