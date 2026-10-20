import { createFileRoute } from '@tanstack/react-router'
import { TaxClasses } from '@/features/tax/tax-classes'


export const Route = createFileRoute('/_authenticated/tax/tax-classes/')({
  component: TaxClasses,
})
