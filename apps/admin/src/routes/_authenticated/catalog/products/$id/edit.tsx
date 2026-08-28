import { createFileRoute } from '@tanstack/react-router'
import { ProductForm } from '@/features/catalog/products/form'


export const Route = createFileRoute('/_authenticated/catalog/products/$id/edit')({
  component: ProductForm,
})