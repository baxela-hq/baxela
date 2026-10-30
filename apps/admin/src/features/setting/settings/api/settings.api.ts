import { getRequest, patchRequest } from '@/shared/lib/api-client'
import { type AllResponse } from '@/shared/types/common.types'
import type { Setting, SettingRequest } from '../data/schema'

const BASE_URL = 'setting/admin/settings'

export async function updateSettings(
  request: SettingRequest[]
): Promise<Setting[]> {
  const { data } = await patchRequest<AllResponse<Setting>, SettingRequest[]>(
    BASE_URL,
    request
  )
  return data as Setting[]
}

export async function fetchSettings(queryParams = {}): Promise<Setting[]> {
  const { data } = await getRequest<AllResponse<Setting>>(BASE_URL, queryParams)
  return data as Setting[]
}

// Grouped PHP timezone identifiers { region: identifiers[] } — the
// timezone dropdown's options (computed server-side, no storage).
export async function fetchTimezones(): Promise<Record<string, string[]>> {
  const { data } = await getRequest<{ data: Record<string, string[]> }>(
    'core/admin/timezones'
  )
  return data
}
