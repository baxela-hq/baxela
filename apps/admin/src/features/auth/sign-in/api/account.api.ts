import { getRequest } from '@/shared/lib/api-client'
import { type SingleResponse } from '@/shared/types/common.types.ts'

const BASE_URL = 'auth/admin/account'

export interface AdminAccountRole {
  id: number
  name: string
}

export interface AdminAccount {
  /** Numeric user id — drives the private `user.{id}` Reverb channel. */
  id: number
  email: string
  roles: AdminAccountRole[]
}

/**
 * Admin-scope identity of the signed-in user. A 403 here means the user is
 * not staff — this call is the admin panel's entry gate.
 */
export async function fetchAdminAccount(): Promise<AdminAccount> {
  const { data } = await getRequest<SingleResponse<AdminAccount>>(BASE_URL)
  return data
}
