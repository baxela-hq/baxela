import { createFileRoute } from '@tanstack/react-router'
import { AttributeGroups } from '@/features/catalog/attribute-groups'

export const Route = createFileRoute('/_authenticated/catalog/attributes/groups')({
  component: AttributeGroups,
})
