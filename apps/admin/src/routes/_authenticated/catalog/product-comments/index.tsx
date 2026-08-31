import { createFileRoute } from '@tanstack/react-router'
import { ProductComments } from '@/features/catalog/product-comments'

// Passthrough schema: keeps Laravel-style params (`filter[status]`,
// `filter[product_id]`, `sort`, `page`, `pageSize`) in the URL unvalidated,
// like sibling catalog lists, while typing the search for the feature.
export const Route = createFileRoute('/_authenticated/catalog/product-comments/')({
  validateSearch: (search: Record<string, unknown>) => search,
  component: ProductComments,
})
