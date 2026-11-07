import { createFileRoute, redirect } from '@tanstack/react-router'
import { FailedJobs } from '@/features/failed-jobs'
import { hasPermission } from '@/shared/hooks/use-permissions'

export const Route = createFileRoute('/_authenticated/failed-jobs/')({
  beforeLoad: () => {
    if (!hasPermission('core.admin.failed-jobs.list')) {
      throw redirect({ to: '/403' })
    }
  },
  validateSearch: (search: Record<string, unknown>) => search,
  component: FailedJobs,
})
