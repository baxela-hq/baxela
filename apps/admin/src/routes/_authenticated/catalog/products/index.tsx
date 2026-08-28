import { createFileRoute } from '@tanstack/react-router'
import { Products } from '@/features/catalog/products'


export const Route = createFileRoute('/_authenticated/catalog/products/')({
  component: Products,
})