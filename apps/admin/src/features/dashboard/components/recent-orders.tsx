import { useNavigate } from '@tanstack/react-router'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'
import { useFormatPrice } from '@/shared/hooks/use-format-price'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { statusTypes } from '@/features/order/orders/data/data.ts'
import {
  FeatureRoutes as OrderRoutes,
  Locales as OrderLocales,
} from '@/features/order/orders/data/routes'
import { type Order } from '@/features/order/orders/data/schema'
import { Locales } from '../data/routes'

export function RecentOrders({ orders }: { orders: Order[] }) {
  const { t } = useAppTranslation(Locales.DASHBOARD)
  const { tStatus: tOrderStatus } = useAppTranslation(OrderLocales.ORDER)
  const navigate = useNavigate()
  const formatPrice = useFormatPrice()
  const { formatDateTime } = useFormatDateTime()

  if (orders.length === 0) {
    return (
      <p className='text-sm text-muted-foreground'>
        {t('recent-orders.empty')}
      </p>
    )
  }

  return (
    <div className='space-y-6'>
      {orders.map((order) => {
        const showUrl = OrderRoutes.SHOW.replace('$id', order.id.toString())

        return (
          <div key={order.id} className='flex items-center gap-4'>
            <div className='flex flex-1 flex-wrap items-center justify-between gap-2'>
              <div className='space-y-1'>
                <button
                  type='button'
                  className='text-sm leading-none font-medium hover:underline'
                  onClick={() => navigate({ to: showUrl })}
                >
                  {order.order_code}
                </button>
                <p className='text-sm text-muted-foreground'>
                  {formatDateTime(order.created_at)}
                </p>
              </div>
              <div className='flex items-center gap-3'>
                <Badge
                  variant='outline'
                  className={cn('text-xs', statusTypes.get(order.status))}
                >
                  {tOrderStatus(`status.${order.status}`)}
                </Badge>
                <div className='font-medium'>
                  {formatPrice(order.total_amount, order.currency_id)}
                </div>
              </div>
            </div>
          </div>
        )
      })}
    </div>
  )
}
