import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createInventoryStock,
  deleteInventoryStock,
  updateInventoryStock,
} from '../api/inventory-stocks.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type InventoryStock, type InventoryStockForm } from '../data/schema'

/**
 * Create or update an inventory stock. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveInventoryStock() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.INVENTORY_STOCK)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: InventoryStockForm }) =>
      id ? updateInventoryStock(id, data) : createInventoryStock(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('inventory-stock'),
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
 * Delete a single stock. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteInventoryStock() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (stock: InventoryStock) => deleteInventoryStock(stock.id.toString()),
    onSuccess: (_result, stock) => {
      showSubmittedData(stock, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, stock) => {
      showSubmittedData(stock, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many stocks. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteInventoryStocks() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (stocks: InventoryStock[]) => {
      for (const stock of stocks) {
        await deleteInventoryStock(stock.id.toString())
      }
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
