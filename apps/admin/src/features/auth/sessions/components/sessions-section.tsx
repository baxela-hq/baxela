import { useAppTranslation } from '@/hooks/useAppTranslation'
import { SkeletonWidget } from '@/components/shared/skeleton-widget.tsx'
import { Locales } from '../data/routes'
import { useAccountSessions, useAccountSessionMutations } from '../hooks/use-sessions'
import { SessionsTable } from './sessions-table'

/**
 * "Active sessions" under account security (S.2): the signed-in admin's
 * own devices. Revoking the current session or "sign out everywhere"
 * ends this session too — the warning copy says so.
 */
export function SessionsSection() {
  const { t } = useAppTranslation(Locales.SESSIONS)
  const { data: sessions, isLoading } = useAccountSessions()
  const { revoke, revokeAll } = useAccountSessionMutations()

  if (isLoading) return <SkeletonWidget />

  return (
    <div className='grid gap-3'>
      <p className='text-sm text-muted-foreground'>{t('self-description')}</p>
      <SessionsTable
        sessions={sessions ?? []}
        isPending={revoke.isPending || revokeAll.isPending}
        revokeAllLabel={t('sign-out-everywhere')}
        onRevoke={(session) => revoke.mutate(session.id)}
        onRevokeAll={() => revokeAll.mutate()}
      />
    </div>
  )
}
