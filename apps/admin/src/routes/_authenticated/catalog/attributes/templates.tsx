import { createFileRoute } from '@tanstack/react-router'
import { AttributeTemplates } from '@/features/catalog/attribute-templates'

export const Route = createFileRoute(
  '/_authenticated/catalog/attributes/templates'
)({
  component: AttributeTemplates,
})
