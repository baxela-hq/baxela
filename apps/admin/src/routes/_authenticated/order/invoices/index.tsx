import { createFileRoute } from '@tanstack/react-router'
import { Invoices } from '@/features/order/invoices'

export const Route = createFileRoute('/_authenticated/order/invoices/')({
  component: Invoices,
})
