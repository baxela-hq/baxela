import { createFileRoute } from '@tanstack/react-router'
import { Returns } from '@/features/order/returns'


export const Route = createFileRoute('/_authenticated/order/returns/')({
  component: Returns,
})
