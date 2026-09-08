import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createMenuLink,
  deleteMenuLink,
  updateMenuLink,
} from '../api/menu-links.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type MenuLink, type MenuLinkForm, type MenuLinkPayload, toPayload } from '../data/schema'

/** Build the full PATCH payload for a link with a (possibly new) position. */
function reorderPayload(link: MenuLink): MenuLinkPayload {
  return toPayload({
    parent_id: link.parent_id === null ? 'none' : link.parent_id.toString(),
    url: link.url,
    target: link.target,
    position: (link.position ?? 1).toString(),
    translations: link.translations,
  })
}

/**
 * Create or update a menu-link (nested under its parent menu). The API
 * call, success toast and cache invalidation live here; the consuming
 * component only handles UI concerns (close dialog, reset form).
 */
export function useSaveMenuLink() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.MENU_LINK)

  return useMutation({
    mutationFn: ({
      menuId,
      id,
      data,
    }: {
      menuId: string
      id?: string
      data: MenuLinkForm
    }) =>
      id
        ? updateMenuLink(menuId, id, toPayload(data))
        : createMenuLink(menuId, toPayload(data)),
    onSuccess: async (_result, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('link'),
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
 * Delete a single menu-link (its whole subtree goes with it). No list
 * invalidation on purpose — the list refresh still comes from the dialogs
 * host (see components/dialogs.tsx).
 */
export function useDeleteMenuLink() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: ({ menuId, link }: { menuId: string; link: MenuLink }) =>
      deleteMenuLink(menuId, link.id.toString()),
    onSuccess: (_result, { link }) => {
      showSubmittedData(link, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, { link }) => {
      showSubmittedData(link, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many menu-links (each knows its parent menu). The
 * consuming component wraps mutateAsync in its own toast.promise for
 * loading/success/error feedback.
 */
export function useBulkDeleteMenuLinks() {
  return useMutation({
    mutationFn: async (links: MenuLink[]) => {
      for (const link of links) {
        await deleteMenuLink(link.menu_id.toString(), link.id.toString())
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}

/**
 * Persist a new sibling order produced by the sort dialog: a full PATCH per
 * changed link (position + untouched fields). The consuming component wraps
 * mutateAsync in its own toast.promise.
 */
export function useReorderMenuLinks() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      menuId,
      links,
    }: {
      menuId: string
      links: MenuLink[]
    }) => {
      for (const link of links) {
        await updateMenuLink(menuId, link.id.toString(), reorderPayload(link))
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
