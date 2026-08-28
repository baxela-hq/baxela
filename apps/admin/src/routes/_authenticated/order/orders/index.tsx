import { createFileRoute } from '@tanstack/react-router'
import { Orders } from '@/features/order/orders'


export const Route = createFileRoute('/_authenticated/order/orders/')({
  component: Orders,
})