import { useQuery } from '@tanstack/react-query'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { fetchFailedJob, fetchFailedJobs } from '../api/failed-job.api'
import { FeatureRoutes } from '../data/routes'
import { type FailedJob } from '../data/schema'

/** Paginated failed-job list (server-side pagination/sort via URL search). */
export function useFailedJobsList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<FailedJob>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchFailedJobs(search),
  })
}

/** Full detail row for the view dialog (full exception + redacted payload). */
export function useFailedJob(uuid: string | null) {
  return useQuery({
    queryKey: [FeatureRoutes.CACHE_KEY, 'detail', uuid],
    queryFn: () => fetchFailedJob(uuid as string),
    enabled: !!uuid,
  })
}
