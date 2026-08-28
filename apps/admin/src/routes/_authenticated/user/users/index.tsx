import { createFileRoute } from '@tanstack/react-router'
import { Users } from '@/features/user/users'


export const Route = createFileRoute('/_authenticated/user/users/')({
  component: Users,
})