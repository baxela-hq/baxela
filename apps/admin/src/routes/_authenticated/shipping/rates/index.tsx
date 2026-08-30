import { createFileRoute } from '@tanstack/react-router'
import { Rates } from '@/features/shipping/rates'


export const Route = createFileRoute('/_authenticated/shipping/rates/')({
  component: Rates,
})
