import { useFormatPrice } from '@/shared/hooks/use-format-price'
import { Ban, BadgeCheck, CircleDollarSign, UserPlus } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Locales as OrderLocales } from '@/features/order/orders/data/routes'
import { formatCount } from '../data/format'
import { Locales } from '../data/routes'
import { type AuthStats, type OrderStats } from '../data/schema'
import { AnalyticsChart } from './analytics-chart'

type AnalyticsProps = {
  orderStats?: OrderStats
  authStats?: AuthStats
  isLoading: boolean
}

export function Analytics({
  orderStats,
  authStats,
  isLoading,
}: AnalyticsProps) {
  const { t } = useAppTranslation(Locales.DASHBOARD)
  const { tStatus: tOrderStatus } = useAppTranslation(OrderLocales.ORDER)
  const formatPrice = useFormatPrice()

  const statusItems = (orderStats?.orders_by_status ?? []).map((entry) => ({
    name: tOrderStatus(`status.${entry.status}`),
    value: entry.count,
  }))
  const productItems = (orderStats?.top_products ?? []).map((entry) => ({
    name: entry.name,
    value: entry.quantity,
  }))

  return (
    <div className='space-y-4'>
      <Card>
        <CardHeader>
          <CardTitle>{t('analytics.orders-overview.title')}</CardTitle>
          <CardDescription>
            {t('analytics.orders-overview.description')}
          </CardDescription>
        </CardHeader>
        <CardContent className='px-6'>
          {isLoading ? (
            <Skeleton className='h-[300px] w-full' />
          ) : (
            <AnalyticsChart data={orderStats?.orders_per_day ?? []} />
          )}
        </CardContent>
      </Card>
      <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>
        <Card>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
            <CardTitle className='text-sm font-medium'>
              {t('analytics.stat-cards.avg-order-value')}
            </CardTitle>
            <CircleDollarSign className='h-4 w-4 text-muted-foreground' />
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>
              {isLoading ? (
                <Skeleton className='h-8 w-28' />
              ) : orderStats?.avg_order_value ? (
                formatPrice(orderStats.avg_order_value)
              ) : (
                '—'
              )}
            </div>
            <p className='text-xs text-muted-foreground'>
              {t('analytics.stat-cards.avg-order-value-caption')}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
            <CardTitle className='text-sm font-medium'>
              {t('analytics.stat-cards.paid-orders')}
            </CardTitle>
            <BadgeCheck className='h-4 w-4 text-muted-foreground' />
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>
              {isLoading ? (
                <Skeleton className='h-8 w-20' />
              ) : (
                formatCount(orderStats?.paid_orders_count ?? 0)
              )}
            </div>
            <p className='text-xs text-muted-foreground'>
              {t('analytics.stat-cards.paid-orders-caption')}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
            <CardTitle className='text-sm font-medium'>
              {t('analytics.stat-cards.cancelled-orders')}
            </CardTitle>
            <Ban className='h-4 w-4 text-muted-foreground' />
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>
              {isLoading ? (
                <Skeleton className='h-8 w-20' />
              ) : (
                formatCount(orderStats?.cancelled_orders_count ?? 0)
              )}
            </div>
            <p className='text-xs text-muted-foreground'>
              {t('analytics.stat-cards.cancelled-orders-caption')}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
            <CardTitle className='text-sm font-medium'>
              {t('analytics.stat-cards.new-customers')}
            </CardTitle>
            <UserPlus className='h-4 w-4 text-muted-foreground' />
          </CardHeader>
          <CardContent>
            <div className='text-2xl font-bold'>
              {isLoading ? (
                <Skeleton className='h-8 w-20' />
              ) : (
                formatCount(authStats?.new_customers_this_month ?? 0)
              )}
            </div>
            <p className='text-xs text-muted-foreground'>
              {t('analytics.stat-cards.new-customers-caption')}
            </p>
          </CardContent>
        </Card>
      </div>
      <div className='grid grid-cols-1 gap-4 lg:grid-cols-7'>
        <Card className='col-span-1 lg:col-span-4'>
          <CardHeader>
            <CardTitle>{t('analytics.orders-by-status.title')}</CardTitle>
            <CardDescription>
              {t('analytics.orders-by-status.description')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {statusItems.length > 0 ? (
              <SimpleBarList
                items={statusItems}
                barClass='bg-primary'
                valueFormatter={(n) => `${n}`}
              />
            ) : (
              <p className='text-sm text-muted-foreground'>
                {t('analytics.orders-by-status.empty')}
              </p>
            )}
          </CardContent>
        </Card>
        <Card className='col-span-1 lg:col-span-3'>
          <CardHeader>
            <CardTitle>{t('analytics.top-products.title')}</CardTitle>
            <CardDescription>
              {t('analytics.top-products.description')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {productItems.length > 0 ? (
              <SimpleBarList
                items={productItems}
                barClass='bg-muted-foreground'
                valueFormatter={(n) => `${n}`}
              />
            ) : (
              <p className='text-sm text-muted-foreground'>
                {t('analytics.top-products.empty')}
              </p>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

function SimpleBarList({
  items,
  valueFormatter,
  barClass,
}: {
  items: { name: string; value: number }[]
  valueFormatter: (n: number) => string
  barClass: string
}) {
  const max = Math.max(...items.map((i) => i.value), 1)
  return (
    <ul className='space-y-3'>
      {items.map((i) => {
        const width = `${Math.round((i.value / max) * 100)}%`
        return (
          <li key={i.name} className='flex items-center justify-between gap-3'>
            <div className='min-w-0 flex-1'>
              <div className='mb-1 truncate text-xs text-muted-foreground'>
                {i.name}
              </div>
              <div className='h-2.5 w-full rounded-full bg-muted'>
                <div
                  className={`h-2.5 rounded-full ${barClass}`}
                  style={{ width }}
                />
              </div>
            </div>
            <div className='ps-2 text-xs font-medium tabular-nums'>
              {valueFormatter(i.value)}
            </div>
          </li>
        )
      })}
    </ul>
  )
}
