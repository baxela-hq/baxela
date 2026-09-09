import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import { type AllResponse, type SingleResponse } from '@/shared/types/common.types'
import { type Role, type RoleForm } from '../data/schema'

const BASE_URL = 'auth/admin/roles'

export async function fetchRoles(): Promise<Role[]> {
  const { data } = await getRequest<AllResponse<Role>>(BASE_URL)
  return data as Role[]
}

export async function createRole(request: RoleForm): Promise<Role> {
  const { data } = await postRequest<SingleResponse<Role>, RoleForm>(BASE_URL, request)
  return data as Role
}

export function updateRole(id: string, data: RoleForm): Promise<Role> {
  return patchRequest<Role, RoleForm>(`${BASE_URL}/${id}`, data)
}

export function deleteRole(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
