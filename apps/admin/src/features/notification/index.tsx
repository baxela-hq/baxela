import { useState } from 'react'
import { CheckCheck } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Locales } from './data/routes'
import { useMarkAllNotificationsRead, useMarkNotificationRead } from './hooks/use-notification-mutations'
import { useNotificationsList, useUnreadNotificationsCount } from './hooks/use-notifications'
import { NotificationItem } from './components/notification-item'

type InboxFilter = 'all' | 'unread'

export function Notifications() {
  const [filter, setFilter] = useState<InboxFilter>('all')
  const [page, setPage] = useState(1)

  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { t, tLabel } = useAppTranslation(Locales.NOTIFICATION)

  const { data: countData } = useUnreadNotificationsCount()
  const search =
    filter === 'unread'
      ? { 'filter[unread]': 'true', page: String(page) }
      : { page: String(page) }
  const { data, isLoading, isSuccess } = useNotificationsList(search)

  const markRead = useMarkNotificationRead()
  const markAll = useMarkAllNotificationsRead()

  const unread = countData?.data.unread_count ?? 0
  const items = data?.data ?? []
  const meta = data?.meta

  return (
    <>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle('index.title', { entity: tLabel('notification') })}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle', { entity: tLabel('notification') })}
            </p>
          </div>
          <Button
            size='sm'
            className='gap-1.5'
            disabled={markAll.isPending || unread === 0}
            onClick={() => markAll.mutate()}
          >
            <CheckCheck size={16} />
            {t('form.actions.mark-all-read')}
          </Button>
        </div>

        <Tabs
          value={filter}
          onValueChange={(value) => {
            setFilter(value as InboxFilter)
            setPage(1)
          }}
        >
          <TabsList>
            <TabsTrigger value='all'>{t('form.labels.all')}</TabsTrigger>
            <TabsTrigger value='unread'>
              {t('form.labels.unread')}
              {unread > 0 ? ` (${unread > 99 ? '99+' : unread})` : ''}
            </TabsTrigger>
          </TabsList>
        </Tabs>

        {isLoading && <SkeletonWidget />}
        {isSuccess && (
          <div className='rounded-lg border'>
            {items.length === 0 ? (
              <p className='px-4 py-16 text-center text-sm text-muted-foreground'>
                {t('form.labels.empty')}
              </p>
            ) : (
              <div className='divide-y'>
                {items.map((notification) => (
                  <NotificationItem
                    key={notification.id}
                    notification={notification}
                    onRead={(id) => markRead.mutate(id)}
                  />
                ))}
              </div>
            )}
            {(meta?.last_page ?? 1) > 1 && (
              <div className='flex items-center justify-between border-t px-4 py-2'>
                <Button
                  variant='outline'
                  size='sm'
                  disabled={page <= 1}
                  onClick={() => setPage((current) => current - 1)}
                >
                  {t('form.actions.previous')}
                </Button>
                <p className='text-sm text-muted-foreground'>
                  {meta?.current_page} / {meta?.last_page}
                </p>
                <Button
                  variant='outline'
                  size='sm'
                  disabled={page >= (meta?.last_page ?? 1)}
                  onClick={() => setPage((current) => current + 1)}
                >
                  {t('form.actions.next')}
                </Button>
              </div>
            )}
          </div>
        )}
      </Main>
    </>
  )
}
