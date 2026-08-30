import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createZone,
  deleteZone,
  updateZone,
} from '../api/zones.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Zone, type ZoneForm } from '../data/schema'

/**
 * Create or update a shipping zone. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveZone() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.ZONE)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: ZoneForm }) =>
      id ? updateZone(id, data) : createZone(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('zone'),
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

/**
 * Delete a single zone. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteZone() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (zone: Zone) => deleteZone(zone.id.toString()),
    onSuccess: (_result, zone) => {
      showSubmittedData(zone, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, zone) => {
      showSubmittedData(zone, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many zones. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteZones() {
  return useMutation({
    mutationFn: async (zones: Zone[]) => {
      for (const zone of zones) {
        await deleteZone(zone.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
