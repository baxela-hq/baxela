import { createFileRoute } from '@tanstack/react-router'
import { Options } from '@/features/catalog/options'


export const Route = createFileRoute('/_authenticated/catalog/options/')({
  component: Options,
})