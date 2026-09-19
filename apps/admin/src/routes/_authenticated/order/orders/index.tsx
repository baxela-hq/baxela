import { createFileRoute } from '@tanstack/react-router'
import { Orders } from '@/features/order/orders'

// Passthrough schema: keeps Laravel-style params (`filter[order_code]`,
// `filter[status]`, `sort`, `page`, `pageSize`) in the URL unvalidated,
// like sibling lists, while typing the search for the feature.
export const Route = createFileRoute('/_authenticated/order/orders/')({
  validateSearch: (search: Record<string, unknown>) => search,
  component: Orders,
})
