import { createFileRoute } from '@tanstack/react-router'
import { PromotionForm } from '@/features/discount/promotions/form'


export const Route = createFileRoute('/_authenticated/discount/promotions/$id/edit')({
  component: PromotionForm,
})
