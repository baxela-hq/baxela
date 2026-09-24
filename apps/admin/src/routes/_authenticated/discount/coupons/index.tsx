import { createFileRoute } from '@tanstack/react-router'
import { Coupons } from '@/features/discount/coupons'

export const Route = createFileRoute('/_authenticated/discount/coupons/')({
  component: Coupons,
})
