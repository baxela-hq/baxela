import { createFileRoute } from '@tanstack/react-router'
import { Shipments } from '@/features/shipping/shipments'


export const Route = createFileRoute('/_authenticated/shipping/shipments/')({
  component: Shipments,
})
