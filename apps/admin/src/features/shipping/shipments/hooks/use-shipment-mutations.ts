import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createShipment,
  updateShipment,
} from '../api/shipments.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type ShipmentForm } from '../data/schema'

/**
 * Create or update a shipment. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 *
 * There is no delete — shipments are immutable records that only move
 * forward through their status lifecycle.
 */
export function useSaveShipment() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.SHIPMENT)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: ShipmentForm }) =>
      id ? updateShipment(id, data) : createShipment(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('shipment'),
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
