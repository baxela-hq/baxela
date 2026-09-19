import { useState } from 'react'
import { Link } from '@tanstack/react-router'
import { Bell, CheckCheck } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import { Locales } from '../data/routes'
import { useMarkAllNotificationsRead, useMarkNotificationRead } from '../hooks/use-notification-mutations'
import { useNotificationsList, useUnreadNotificationsCount } from '../hooks/use-notifications'
import { NotificationItem } from './notification-item'

/** Header bell: unread badge polled every 30s, recent items on click. */
export function NotificationsMenu() {
  const [open, setOpen] = useState(false)
  const { t } = useAppTranslation(Locales.NOTIFICATION)

  const { data: countData } = useUnreadNotificationsCount()
  const { data, isLoading } = useNotificationsList({}, open)
  const markRead = useMarkNotificationRead()
  const markAll = useMarkAllNotificationsRead()

  const unread = countData?.data.unread_count ?? 0
  const items = data?.data ?? []

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant='ghost'
          size='icon'
          className='relative h-9 w-9'
          aria-label={t('menu.title')}
        >
          <Bell size={18} />
          {unread > 0 && (
            <span className='absolute -end-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-none text-primary-foreground'>
              {unread > 99 ? '99+' : unread}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align='end' className='w-96 p-0'>
        <div className='flex items-center justify-between px-4 py-3'>
          <p className='text-sm font-semibold'>{t('menu.title')}</p>
          {unread > 0 && (
            <Button
              variant='ghost'
              size='sm'
              className='h-8 gap-1.5 px-2 text-xs'
              disabled={markAll.isPending}
              onClick={() => markAll.mutate()}
            >
              <CheckCheck size={14} />
              {t('form.actions.mark-all-read')}
            </Button>
          )}
        </div>
        <Separator />
        <ScrollArea className='h-80'>
          {isLoading && items.length === 0 ? (
            <div className='space-y-3 p-4'>
              {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className='flex items-center gap-3'>
                  <Skeleton className='size-9 shrink-0 rounded-full' />
                  <div className='flex-1 space-y-1.5'>
                    <Skeleton className='h-3.5 w-3/4' />
                    <Skeleton className='h-3 w-1/2' />
                  </div>
                </div>
              ))}
            </div>
          ) : items.length === 0 ? (
            <p className='px-4 py-10 text-center text-sm text-muted-foreground'>
              {t('menu.empty')}
            </p>
          ) : (
            <div className='divide-y'>
              {items.map((notification) => (
                <NotificationItem
                  key={notification.id}
                  notification={notification}
                  onRead={(id) => markRead.mutate(id)}
                  compact
                />
              ))}
            </div>
          )}
        </ScrollArea>
        <Separator />
        <div className='p-2'>
          <Button variant='ghost' size='sm' className='w-full' asChild>
            <Link to='/notifications'>{t('menu.view-all')}</Link>
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  )
}
