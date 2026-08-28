import { createFileRoute } from '@tanstack/react-router'
import { AttributesLayout } from '@/features/catalog/attributes/layout'

export const Route = createFileRoute('/_authenticated/catalog/attributes')({
  component: AttributesLayout,
})
