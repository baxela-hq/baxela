import { createFileRoute } from '@tanstack/react-router'
import { Notifications } from '@/features/notification'

export const Route = createFileRoute('/_authenticated/notifications/')({
  component: Notifications,
})
