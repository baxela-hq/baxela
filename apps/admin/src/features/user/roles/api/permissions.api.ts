import { getRequest } from '@/shared/lib/api-client'
import { type AllResponse } from '@/shared/types/common.types'
import { type Permission } from '../data/schema'

const BASE_URL = 'auth/admin/permissions'

/** Full permission catalog (route-derived); group by the first name segment on the client. */
export async function fetchPermissions(): Promise<Permission[]> {
  const { data } = await getRequest<AllResponse<Permission>>(BASE_URL)
  return data as Permission[]
}
