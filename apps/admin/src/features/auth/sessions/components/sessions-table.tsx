import { Loader2, MonitorSmartphone, ShieldCheck, Trash2 } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Locales } from '../data/routes'
import { type Session } from '../data/schema'

interface SessionsTableProps {
  sessions: Session[]
  onRevoke: (session: Session) => void
  onRevokeAll: () => void
  /** Disables the buttons while a mutation is in flight. */
  isPending?: boolean
  /**
   * Overrides the bulk button label — self-service says "sign out
   * everywhere" (it kills this session too), the admin dialog says
   * "revoke all sessions".
   */
  revokeAllLabel?: string
}

/**
 * Shared session list rendering (S.2): device name, timestamps, expiry,
 * current-session badge, per-row revoke, and revoke-all. Used by both
 * the self-service security page and the super-admin user dialog — only
 * the data source and revoke callbacks differ.
 */
export function SessionsTable({
  sessions,
  onRevoke,
  onRevokeAll,
  isPending = false,
  revokeAllLabel,
}: SessionsTableProps) {
  const { t } = useAppTranslation(Locales.SESSIONS)

  const formatDateTime = (value: string | null) =>
    value ? new Date(value).toLocaleString() : '—'

  return (
    <div className='grid gap-3'>
      <div className='grid gap-2'>
        {sessions.map((session) => (
          <div
            key={session.id}
            className='flex items-center justify-between gap-3 rounded-md border p-3'
          >
            <div className='grid min-w-0 gap-1'>
              <div className='flex items-center gap-2'>
                <MonitorSmartphone className='h-4 w-4 shrink-0 text-muted-foreground' />
                <span className='truncate text-sm font-medium'>
                  {session.name}
                </span>
                {session.is_current && (
                  <span className='inline-flex shrink-0 items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary'>
                    <ShieldCheck className='h-3 w-3' />
                    {t('current-badge')}
                  </span>
                )}
              </div>
              <div className='text-xs text-muted-foreground'>
                {t('last-used')}: {formatDateTime(session.last_used_at)}
                {' · '}
                {t('created')}: {formatDateTime(session.created_at)}
                {' · '}
                {t('expires')}: {formatDateTime(session.expires_at)}
              </div>
            </div>
            <Button
              variant='ghost'
              size='icon'
              className='shrink-0 text-muted-foreground hover:text-destructive'
              title={t('revoke-one')}
              disabled={isPending}
              onClick={() => onRevoke(session)}
            >
              {isPending ? (
                <Loader2 className='h-4 w-4 animate-spin' />
              ) : (
                <Trash2 className='h-4 w-4' />
              )}
            </Button>
          </div>
        ))}
        {sessions.length === 0 && (
          <p className='rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground'>
            {t('empty')}
          </p>
        )}
      </div>

      {sessions.length > 0 && (
        <Button
          variant='destructive'
          className='w-fit'
          disabled={isPending}
          onClick={onRevokeAll}
        >
          {isPending && <Loader2 className='animate-spin' />}
          {revokeAllLabel ?? t('revoke-all')}
        </Button>
      )}
    </div>
  )
}
