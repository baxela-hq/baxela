import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createCoupon,
  deleteCoupon,
  updateCoupon,
} from '../api/coupons.api'
import { toPayload, type Coupon, type CouponForm } from '../data/schema'
import { FeatureRoutes, Locales } from '../data/routes'

/**
 * Create or update a coupon. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close drawer, reset form) in its per-call onSuccess.
 */
export function useSaveCoupon() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.COUPON)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: CouponForm }) =>
      id
        ? updateCoupon(id, toPayload(data))
        : createCoupon(toPayload(data)),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('coupon'),
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
 * Delete a single coupon. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteCoupon() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (coupon: Coupon) => deleteCoupon(coupon.id.toString()),
    onSuccess: (_result, coupon) => {
      showSubmittedData(coupon, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, coupon) => {
      showSubmittedData(coupon, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many coupons. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteCoupons() {
  return useMutation({
    mutationFn: async (coupons: Coupon[]) => {
      for (const coupon of coupons) {
        await deleteCoupon(coupon.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
