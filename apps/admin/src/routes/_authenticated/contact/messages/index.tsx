import { createFileRoute } from '@tanstack/react-router'
import { ContactMessages } from '@/features/contact/messages'

// Passthrough schema: keeps Laravel-style params (`filter[status]`,
// `filter[name]`, `sort`, `page`, `pageSize`) in the URL unvalidated,
// like sibling lists, while typing the search for the feature.
export const Route = createFileRoute('/_authenticated/contact/messages/')({
  validateSearch: (search: Record<string, unknown>) => search,
  component: ContactMessages,
})
