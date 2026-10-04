import { createFileRoute } from '@tanstack/react-router'
import { Tickets } from '@/features/support/tickets'

// Passthrough schema: keeps Laravel-style params (`filter[status]`,
// `filter[subject]`, `sort`, `page`, `pageSize`) in the URL unvalidated,
// like sibling lists, while typing the search for the feature.
export const Route = createFileRoute('/_authenticated/support/tickets/')({
  validateSearch: (search: Record<string, unknown>) => search,
  component: Tickets,
})
