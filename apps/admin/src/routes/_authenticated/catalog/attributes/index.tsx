import { createFileRoute } from '@tanstack/react-router'
import { Attributes } from '@/features/catalog/attributes'

export const Route = createFileRoute('/_authenticated/catalog/attributes/')({
  component: Attributes,
})
