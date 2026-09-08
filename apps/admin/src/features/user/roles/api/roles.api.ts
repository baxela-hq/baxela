import { getRequest } from '@/shared/lib/api-client'
import { type AllResponse } from '@/shared/types/common.types'

const BASE_URL = 'auth/admin/roles'

export interface Role {
  id: number
  name: string
}

export async function fetchRoles(): Promise<Role[]> {
  const { data } = await getRequest<AllResponse<Role>>(BASE_URL)
  return data as Role[]
}
