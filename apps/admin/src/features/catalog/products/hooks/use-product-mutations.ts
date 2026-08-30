import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { type z } from 'zod'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  createProduct,
  deleteProduct,
  updateProduct,
} from '../api/products.api'
import { FeatureRoutes, Locales } from '../data/routes'
import {
  type formSchema,
  serializeAttributeValues,
  serializeSeo,
  serializeShipping,
  type Product,
  type ProductPayload,
} from '../data/schema'

export type ProductFormValues = z.output<typeof formSchema>

/**
 * Create or update a product (full-page form). Request mapping, success
 * toast and list invalidation live here; the consuming component only
 * refreshes the row / redirects in its aftermath.
 */
export function useSaveProduct() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT)

  return useMutation({
    mutationFn: ({ id, values }: { id: number | null; values: ProductFormValues }) => {
      const postRequest: ProductPayload = {
        ...values,
        // The array order is the source of truth — positions are re-sequenced
        // so the first image is always position 1 (featured) after reordering.
        images: values.images.map((image, index) => ({
          ...image,
          position: index + 1,
        })),
        // The backend expects flat rows (multiselect = repeated attribute_id)
        // and rejects rows with an empty value slot.
        attribute_values: serializeAttributeValues(values.attribute_values),
        // The API requires a fixed shape: every key present, null for unused
        // values, and a unit only alongside its value(s).
        shipping: serializeShipping(values.shipping),
        // The API maps the seo language code to a language_id server-side —
        // the payload carries the code only.
        seo: serializeSeo(values.seo),
      }
      return id
        ? updateProduct(id.toString(), postRequest)
        : createProduct(postRequest)
    },
    onSuccess: (product, { id }) => {
      toast.success(
        tMessage(`success.record.${id ? 'updated' : 'created'}`, {
          name: tLabel('product'),
        })
      )
      // not awaited: the create-path redirect must not wait for the list refetch
      queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
      return product
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })
}

/**
 * Delete a single product, then refresh the list cache before the toast.
 */
export function useDeleteProduct() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  return useMutation({
    mutationFn: (product: Product) => deleteProduct(product.id.toString()),
    onSuccess: async (_result, product) => {
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
      showSubmittedData(product, tMessage('success.record.deleted_general'))
    },
    onError: (_err: unknown, product) => {
      showSubmittedData(product, tMessage('error.general'))
    },
  })
}

/**
 * Sequentially delete many products by id. The consuming component wraps
 * mutateAsync in its own toast.promise for loading/success/error feedback.
 */
export function useBulkDeleteProducts() {
  return useMutation({
    mutationFn: async (ids: string[]) => {
      for (const id of ids) {
        await deleteProduct(id)
      }
    },
    onError: () => {
      // feedback handled by the toast.promise wrapper in the consuming component
    },
  })
}
