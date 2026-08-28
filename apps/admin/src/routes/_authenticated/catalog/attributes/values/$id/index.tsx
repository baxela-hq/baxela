import { createFileRoute } from '@tanstack/react-router'
import { AttributeValues } from '@/features/catalog/attribute-values'

export const Route = createFileRoute(
  '/_authenticated/catalog/attributes/values/$id/'
)({
  component: AttributeValues,
})
