import { createFileRoute } from '@tanstack/react-router'
import { Pages } from '@/features/content/pages'


export const Route = createFileRoute('/_authenticated/content/pages/')({
  component: Pages,
})