import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { forgetFailedJob, retryFailedJob } from '../api/failed-job.api'
import { FeatureRoutes } from '../data/routes'

/**
 * Retry = re-queue for another execution attempt — the UI copy makes the
 * possible side-effect repetition explicit before the admin confirms (D4).
 */
export function useRetryFailedJob() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (uuid: string) => retryFailedJob(uuid),
    onSuccess: async () => {
      toast.success('Job re-queued for another attempt')
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error('Retry failed')
    },
  })
}

/** Forget = permanent deletion of the failed record (irreversible, D4). */
export function useForgetFailedJob() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (uuid: string) => forgetFailedJob(uuid),
    onSuccess: async () => {
      toast.success('Failed job deleted')
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error('Delete failed')
    },
  })
}
