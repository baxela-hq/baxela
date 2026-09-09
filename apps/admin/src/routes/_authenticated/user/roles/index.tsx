import { createFileRoute } from '@tanstack/react-router'
import { Roles } from '@/features/user/roles'


export const Route = createFileRoute('/_authenticated/user/roles/')({
  component: Roles,
})
