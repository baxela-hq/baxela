import { type ReactNode } from 'react'
import { useFormatPrice } from '@/shared/hooks/use-format-price'
import { Activity, CreditCard, DollarSign, Users } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { TopNav } from '@/components/layout/top-nav'
import { Search } from '@/components/search'
import { Analytics } from './components/analytics'
import { Overview } from './components/overview'
import { RecentOrders } from './components/recent-orders'
import { formatChangePercent, formatCount } from './data/format'
import { Locales } from './data/routes'
import { useAuthStats, useOrderStats } from './hooks/use-dashboard-stats'

export function Dashboard() {
  const { t, tPageTitle } = useAppTranslation(Locales.DASHBOARD)
  const formatPrice = useFormatPrice()
  const orderQuery = useOrderStats()
  const authQuery = useAuthStats()
  const orderStats = orderQuery.data
  const authStats = authQuery.data

  const changeCaption = (percent: number | null | undefined, key: string) =>
    percent == null
      ? t('stat-cards.no-change-data')
      : t(key, { value: formatChangePercent(percent) })

  const statValue = (node: ReactNode, loading: boolean) =>
    loading ? <Skeleton className='h-8 w-24' /> : node

  const topNav = [
    {
      title: t('top-nav.overview'),
      href: 'dashboard/overview',
      isActive: true,
      disabled: false,
    },
    {
      title: t('top-nav.customers'),
      href: 'dashboard/customers',
      isActive: false,
      disabled: true,
    },
    {
      title: t('top-nav.products'),
      href: 'dashboard/products',
      isActive: false,
      disabled: true,
    },
    {
      title: t('top-nav.settings'),
      href: 'dashboard/settings',
      isActive: false,
      disabled: true,
    },
  ]

  return (
    <>
      {/* ===== Top Heading ===== */}
      <Header>
        <TopNav links={topNav} />
        <Search />
        <HeaderActions />
      </Header>

      {/* ===== Main ===== */}
      <Main>
        <div className='mb-2 flex items-center justify-between space-y-2'>
          <h1 className='text-2xl font-bold tracking-tight'>
            {tPageTitle('index.title')}
          </h1>
        </div>
        <Tabs
          orientation='vertical'
          defaultValue='overview'
          className='space-y-4'
        >
          <div className='w-full overflow-x-auto pb-2'>
            <TabsList>
              <TabsTrigger value='overview'>{t('tabs.overview')}</TabsTrigger>
              <TabsTrigger value='analytics'>{t('tabs.analytics')}</TabsTrigger>
              <TabsTrigger value='reports' disabled>
                {t('tabs.reports')}
              </TabsTrigger>
              <TabsTrigger value='notifications' disabled>
                {t('tabs.notifications')}
              </TabsTrigger>
            </TabsList>
          </div>
          <TabsContent value='overview' className='space-y-4'>
            <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>
              <StatCard
                title={t('stat-cards.total-revenue')}
                icon={<DollarSign className='h-4 w-4 text-muted-foreground' />}
                value={statValue(
                  orderStats ? formatPrice(orderStats.total_revenue) : '—',
                  orderQuery.isLoading
                )}
                caption={changeCaption(
                  orderStats?.revenue_change_percent,
                  'stat-cards.total-revenue-change'
                )}
              />
              <StatCard
                title={t('stat-cards.orders')}
                icon={<CreditCard className='h-4 w-4 text-muted-foreground' />}
                value={statValue(
                  orderStats ? formatCount(orderStats.orders_count) : '—',
                  orderQuery.isLoading
                )}
                caption={changeCaption(
                  orderStats?.orders_change_percent,
                  'stat-cards.orders-change'
                )}
              />
              <StatCard
                title={t('stat-cards.customers')}
                icon={<Users className='h-4 w-4 text-muted-foreground' />}
                value={statValue(
                  authStats ? formatCount(authStats.customers_count) : '—',
                  authQuery.isLoading
                )}
                caption={changeCaption(
                  authStats?.customers_change_percent,
                  'stat-cards.customers-change'
                )}
              />
              <StatCard
                title={t('stat-cards.pending-orders')}
                icon={<Activity className='h-4 w-4 text-muted-foreground' />}
                value={statValue(
                  orderStats
                    ? formatCount(orderStats.pending_orders_count)
                    : '—',
                  orderQuery.isLoading
                )}
                caption={t('stat-cards.pending-orders-change')}
              />
            </div>
            <div className='grid grid-cols-1 gap-4 lg:grid-cols-7'>
              <Card className='col-span-1 lg:col-span-4'>
                <CardHeader>
                  <CardTitle>{t('overview-card')}</CardTitle>
                </CardHeader>
                <CardContent className='ps-2'>
                  {orderQuery.isLoading ? (
                    <Skeleton className='h-[350px] w-full' />
                  ) : orderStats ? (
                    <Overview data={orderStats.revenue_by_month} />
                  ) : (
                    <p className='text-sm text-muted-foreground'>
                      {t('no-data')}
                    </p>
                  )}
                </CardContent>
              </Card>
              <Card className='col-span-1 lg:col-span-3'>
                <CardHeader>
                  <CardTitle>{t('recent-orders.title')}</CardTitle>
                  <CardDescription>
                    {t('recent-orders.description')}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  {orderQuery.isLoading ? (
                    <Skeleton className='h-[300px] w-full' />
                  ) : orderStats ? (
                    <RecentOrders orders={orderStats.recent_orders} />
                  ) : (
                    <p className='text-sm text-muted-foreground'>
                      {t('no-data')}
                    </p>
                  )}
                </CardContent>
              </Card>
            </div>
          </TabsContent>
          <TabsContent value='analytics' className='space-y-4'>
            <Analytics
              orderStats={orderStats}
              authStats={authStats}
              isLoading={orderQuery.isLoading || authQuery.isLoading}
            />
          </TabsContent>
        </Tabs>
      </Main>
    </>
  )
}

function StatCard({
  title,
  icon,
  value,
  caption,
}: {
  title: string
  icon: ReactNode
  value: ReactNode
  caption: ReactNode
}) {
  return (
    <Card>
      <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
        <CardTitle className='text-sm font-medium'>{title}</CardTitle>
        {icon}
      </CardHeader>
      <CardContent>
        <div className='text-2xl font-bold'>{value}</div>
        <p className='text-xs text-muted-foreground'>{caption}</p>
      </CardContent>
    </Card>
  )
}
