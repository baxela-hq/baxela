import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { SkeletonWidget } from '@/components/shared/skeleton-widget.tsx'
import { SessionsTable } from '@/features/auth/sessions/components/sessions-table'
import { Locales as SessionsLocales } from '@/features/auth/sessions/data/routes'
import { type User } from '../data/schema'
import { useUserSessions } from '../hooks/use-user-sessions'

type SessionsDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: User
}

/**
 * Super-admin forced revocation (S.2): the target user's active
 * sessions with per-row and bulk revoke. Revoking signs the target out
 * on that device immediately; every action lands in the audit trail.
 */
export function SessionsDialog({
  open,
  onOpenChange,
  currentRow,
}: SessionsDialogProps) {
  const { t } = useAppTranslation(SessionsLocales.SESSIONS)
  const { query, revoke, revokeAll } = useUserSessions(open ? currentRow : null)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[85vh] overflow-y-auto sm:max-w-lg'>
        <DialogHeader>
          <DialogTitle>
            {t('admin-dialog-title')} — {currentRow.email}
          </DialogTitle>
          <DialogDescription>{t('admin-dialog-description')}</DialogDescription>
        </DialogHeader>

        {query.isLoading ? (
          <SkeletonWidget />
        ) : (
          <SessionsTable
            sessions={query.data ?? []}
            isPending={revoke.isPending || revokeAll.isPending}
            revokeAllLabel={t('revoke-all')}
            onRevoke={(session) => revoke.mutate(session.id)}
            onRevokeAll={() => revokeAll.mutate()}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}
