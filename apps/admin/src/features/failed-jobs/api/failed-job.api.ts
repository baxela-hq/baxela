import { deleteRequest, getRequest, postRequest } from '@/shared/lib/api-client'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'
import { type FailedJob } from '../data/schema'

const BASE_URL = 'core/admin/failed-jobs'

export function fetchFailedJobs(queryParams = {}) {
  return getRequest<PaginatedResponse<FailedJob>>(BASE_URL, queryParams)
}

export async function fetchFailedJob(uuid: string): Promise<FailedJob> {
  const { data } = await getRequest<SingleResponse<FailedJob>>(`${BASE_URL}/${uuid}`)
  return data as FailedJob
}

/** Re-queue a failed job for another execution attempt (may repeat side effects). */
export function retryFailedJob(uuid: string) {
  return postRequest<null, Record<string, never>>(`${BASE_URL}/${uuid}/retry`, {})
}

/** Permanently delete a failed job record. */
export function forgetFailedJob(uuid: string) {
  return deleteRequest<null>(`${BASE_URL}/${uuid}`)
}
