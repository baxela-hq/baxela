import { createFileRoute } from '@tanstack/react-router'
import { Zones } from '@/features/shipping/zones'


export const Route = createFileRoute('/_authenticated/shipping/zones/')({
  component: Zones,
})
