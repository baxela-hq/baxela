import { createFileRoute } from '@tanstack/react-router'
import { Methods } from '@/features/shipping/methods'


export const Route = createFileRoute('/_authenticated/shipping/methods/')({
  component: Methods,
})
