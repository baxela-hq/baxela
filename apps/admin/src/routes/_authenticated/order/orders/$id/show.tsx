import { createFileRoute } from '@tanstack/react-router'
import { OrderShow } from '@/features/order/orders/show.tsx'


export const Route = createFileRoute('/_authenticated/order/orders/$id/show')({
  component: OrderShow,
})