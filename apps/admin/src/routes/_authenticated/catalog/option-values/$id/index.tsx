import { createFileRoute } from '@tanstack/react-router'
import { OptionValues } from '@/features/catalog/option-values'


export const Route = createFileRoute('/_authenticated/catalog/option-values/$id/')({
  component: OptionValues,
})