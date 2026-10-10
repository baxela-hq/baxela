import { createFileRoute } from '@tanstack/react-router'
import { Brands } from '@/features/catalog/brands'


export const Route = createFileRoute('/_authenticated/catalog/brands/')({
  component: Brands,
})
