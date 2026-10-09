import { createFileRoute } from '@tanstack/react-router'
import { Promotions } from '@/features/discount/promotions'

export const Route = createFileRoute('/_authenticated/discount/promotions/')({
  component: Promotions,
})
