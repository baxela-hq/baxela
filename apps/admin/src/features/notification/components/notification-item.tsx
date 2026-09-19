import { Link } from '@tanstack/react-router'
import { Bell, CreditCard, Inbox, Package, ShieldCheck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'
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
  onRead: (id: number) => void
  compact?: boolean
}

/**
 * One inbox row. Titles/bodies arrive pre-localized from the API; rows
 * carrying an order code deep-link into the orders list filtered by it.
 */
export function NotificationItem({
  notification,
  onRead,
  compact = false,
}: NotificationItemProps) {
  const { formatDateTime } = useFormatDateTime()
  const unread = notification.read_at === null
  const orderCode = notification.meta?.order_code

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

  if (orderCode) {
    return (
      <Link
        to='/order/orders'
        search={{ 'filter[order_code]': orderCode }}
        className={className}
        onClick={() => unread && onRead(notification.id)}
      >
        {content}
      </Link>
    )
  }

  return (
    <button
      type='button'
      className={className}
      onClick={() => unread && onRead(notification.id)}
    >
      {content}
    </button>
  )
}
