import { createFileRoute } from '@tanstack/react-router'
import { Currencies } from '@/features/core/currencies'

export const Route = createFileRoute('/_authenticated/core/currencies/')({
  component: Currencies,
})
