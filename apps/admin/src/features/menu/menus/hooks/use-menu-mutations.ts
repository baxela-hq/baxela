import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createMenu,
  deleteMenu,
  updateMenu,
} from '../api/menus.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Menu, type MenuForm } from '../data/schema'

/**
 * Create or update a menu. The API call, success toast and cache
 * invalidation live here; the consuming component only handles UI concerns
 * (close dialog, reset form) in its per-call onSuccess.
 */
export function useSaveMenu() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.MENU)

  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: MenuForm }) =>
      id ? updateMenu(id, data) : createMenu(data),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('menu'),
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
 * Delete a single menu. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeleteMenu() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (menu: Menu) => deleteMenu(menu.id.toString()),
    onSuccess: (_result, menu) => {
      showSubmittedData(menu, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, menu: Menu) => {
      showSubmittedData(menu, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many menus. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteMenus() {
  return useMutation({
    mutationFn: async (menus: Menu[]) => {
      for (const menu of menus) {
        await deleteMenu(menu.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
