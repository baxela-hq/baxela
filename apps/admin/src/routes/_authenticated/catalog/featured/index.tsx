import { createFileRoute } from '@tanstack/react-router'
import { Featured } from '@/features/catalog/featured'

export const Route = createFileRoute('/_authenticated/catalog/featured/')({
  component: Featured,
})
