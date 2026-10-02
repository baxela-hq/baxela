import { createFileRoute } from '@tanstack/react-router'
import { CatalogData } from '@/features/catalog/data'


export const Route = createFileRoute('/_authenticated/catalog/data/')({
  component: CatalogData,
})
