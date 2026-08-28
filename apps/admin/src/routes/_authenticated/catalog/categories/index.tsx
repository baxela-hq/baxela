import { createFileRoute } from '@tanstack/react-router'
import { Categories } from '@/features/catalog/categories'


export const Route = createFileRoute('/_authenticated/catalog/categories/')({
  component: Categories,
})