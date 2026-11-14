import { createFileRoute, redirect } from '@tanstack/react-router'
import { Webhooks } from '@/features/webhook'
import { hasPermission } from '@/shared/hooks/use-permissions'

export const Route = createFileRoute('/_authenticated/webhooks/')({
  beforeLoad: () => {
    if (!hasPermission('webhooks.admin.endpoints.list')) {
      throw redirect({ to: '/403' })
    }
  },
  validateSearch: (search: Record<string, unknown>) => search,
  component: Webhooks,
})
