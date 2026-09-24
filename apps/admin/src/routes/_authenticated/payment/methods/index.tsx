import { createFileRoute } from '@tanstack/react-router'
import { PaymentMethods } from '@/features/payment/methods'

export const Route = createFileRoute('/_authenticated/payment/methods/')({
  component: PaymentMethods,
})
