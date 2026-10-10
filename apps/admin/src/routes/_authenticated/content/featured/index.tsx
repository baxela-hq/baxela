import { createFileRoute } from '@tanstack/react-router'
import { Featured } from '@/features/content/featured'

export const Route = createFileRoute('/_authenticated/content/featured/')({
  component: Featured,
})
