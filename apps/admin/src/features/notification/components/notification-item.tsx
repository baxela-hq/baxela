import { type LinkProps, Link } from '@tanstack/react-router'
import { Bell, CreditCard, Inbox, Package, ShieldCheck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'
import { resolveNotificationTarget } from '../data/notification-targets'
import { type Notification } from '../data/schema'

const CODE_ICONS = {
  order: Package,
  payment: CreditCard,
  auth: ShieldCheck,
  contact: Inbox,
} as const

function codeIcon(code: string) {
  const prefix = code.split('.')[0]
  const Component = CODE_ICONS[prefix as keyof typeof CODE_ICONS] ?? Bell
  return <Component size={16} />
}

interface NotificationItemProps {
  notification: Notification
  onRead?: (id: number) => void
  compact?: boolean
  /** When set, the whole row links here and clicks never mark it read. */
  to?: LinkProps['to']
}

/**
 * One inbox row. Titles/bodies arrive pre-localized from the API; rows
 * whose meta resolves to an entity (ticket, order) deep-link to its
 * admin page and are marked read on click. Passing `to` overrides
 * both — the header menu rows link to the notifications center, where
 * the admin reads the full message before following the link.
 */
export function NotificationItem({
  notification,
  onRead,
  compact = false,
  to,
}: NotificationItemProps) {
  const { formatDateTime } = useFormatDateTime()
  const unread = notification.read_at === null
  const target = resolveNotificationTarget(notification)

  const content = (
    <>
      <span className='grid size-9 shrink-0 place-items-center rounded-full bg-muted text-muted-foreground'>
        {codeIcon(notification.code)}
      </span>
      <div className='min-w-0 flex-1'>
        <div className='flex items-center gap-2'>
          {unread && (
            <span
              aria-hidden='true'
              className='size-2 shrink-0 rounded-full bg-primary'
            />
          )}
          <p
            className={cn(
              'truncate text-sm',
              unread ? 'font-semibold' : 'font-medium text-muted-foreground'
            )}
          >
            {notification.title}
          </p>
        </div>
        {!compact && (
          <p className='mt-0.5 line-clamp-2 text-sm text-muted-foreground'>
            {notification.body}
          </p>
        )}
        <p className='mt-1 text-xs text-muted-foreground'>
          {formatDateTime(notification.created_at)}
        </p>
      </div>
    </>
  )

  const className = cn(
    'flex w-full items-start gap-3 px-4 py-3 text-start transition-colors hover:bg-muted/50',
    compact && 'items-center py-2.5'
  )

  if (to) {
    return (
      <Link to={to} className={className}>
        {content}
      </Link>
    )
  }

  if (target) {
    return (
      <Link
        to={target.to}
        params={'params' in target ? target.params : undefined}
        search={'search' in target ? target.search : undefined}
        className={className}
        onClick={() => unread && onRead?.(notification.id)}
      >
        {content}
      </Link>
    )
  }

  return (
    <button
      type='button'
      className={className}
      onClick={() => unread && onRead?.(notification.id)}
    >
      {content}
    </button>
  )
}
