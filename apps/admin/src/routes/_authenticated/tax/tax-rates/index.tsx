import { createFileRoute } from '@tanstack/react-router'
import { TaxRates } from '@/features/tax/tax-rates'


export const Route = createFileRoute('/_authenticated/tax/tax-rates/')({
  component: TaxRates,
})
