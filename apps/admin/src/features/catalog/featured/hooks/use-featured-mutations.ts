import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { updateFeatured } from '../api/featured.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type UpdateFeaturedPayload } from '../data/schema'

/**
 * Full sync of the featured selections. On success the featured query is
 * invalidated so the page reloads the persisted order.
 */
export function useUpdateFeatured() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.FEATURED)

  return useMutation({
    mutationFn: (payload: UpdateFeaturedPayload) => updateFeatured(payload),
    onSuccess: async () => {
      toast.success(
        tMessage('success.record.updated', {
          name: tLabel('featured'),
        })
      )
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}
