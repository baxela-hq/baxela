import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createPage,
  deletePage,
  updatePage,
} from '../api/pages.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { type Page, type PageForm } from '../data/schema'

/**
 * Create or update a page (full-page form). Returns the saved page so the
 * consuming component can redirect on create / refresh the row on update.
 */
export function useSavePage() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PAGE)

  return useMutation({
    mutationFn: ({ id, data }: { id: number | null; data: PageForm }) =>
      id ? updatePage(id.toString(), data) : createPage(data),
    onSuccess: (page, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('page'),
        })
      )
      // not awaited: the create-path redirect must not wait for the list refetch
      queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
      return page
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}

/**
 * Delete a single page. No list invalidation on purpose — the list
 * refresh still comes from the dialogs host (see components/dialogs.tsx).
 */
export function useDeletePage() {
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (page: Page) => deletePage(page.id.toString()),
    onSuccess: (_result, page) => {
      showSubmittedData(page, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, page) => {
      showSubmittedData(page, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many pages by id. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeletePages() {
  return useMutation({
    mutationFn: async (ids: string[]) => {
      for (const id of ids) {
        await deletePage(id)
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
