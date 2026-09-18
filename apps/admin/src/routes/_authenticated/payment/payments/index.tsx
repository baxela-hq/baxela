import { createFileRoute } from '@tanstack/react-router'
import { Payments } from '@/features/payment/payments'

export const Route = createFileRoute('/_authenticated/payment/payments/')({
  component: Payments,
})
