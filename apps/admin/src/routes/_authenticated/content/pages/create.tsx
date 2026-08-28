import { createFileRoute } from '@tanstack/react-router'
import { PageForm } from '@/features/content/pages/form'


export const Route = createFileRoute('/_authenticated/content/pages/create')({
  component: PageForm,
})