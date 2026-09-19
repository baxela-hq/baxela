import { type ColumnDef } from '@tanstack/react-table'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'
import { useFormatPrice } from '@/shared/hooks/use-format-price'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge.tsx'
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { statusTypes, paymentStatusTypes } from '../data/data.ts'
import { Locales } from '../data/routes'
import { type Order } from '../data/schema'
import { DataTableRowActions } from './data-table-row-actions'

export const Columns = (): ColumnDef<Order>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.ORDER)
  const { formatDateTime } = useFormatDateTime()
  const formatPrice = useFormatPrice()

  return [
    {
      accessorKey: 'id',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('id')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-36 ps-3'>{row.getValue('id')}</LongText>
      ),
      meta: {
        className: cn(
          'drop-shadow-[0_1px_2px_rgb(0_0_0_/_0.1)] dark:drop-shadow-[0_1px_2px_rgb(255_255_255_/_0.1)]',
          'ps-0.5 max-md:sticky start-6 @4xl/content:table-cell @4xl/content:drop-shadow-none'
        ),
      },
      enableHiding: false,
      enableSorting: true,
    },
    {
      accessorKey: 'order_code',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('order_code')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap'>
          {row.getValue('order_code')}
        </div>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'user_id',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('user_id')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('user_id')}</div>
      ),
      enableSorting: false,
      enableHiding: true,
      filterFn: 'inNumberRange',
    },
    {
      accessorKey: 'status',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('status')} />
      ),
      cell: ({ row }) => {
        const { status } = row.original
        const badgeColor = statusTypes.get(status)
        return (
          <div className='flex space-x-2'>
            <Badge variant='outline' className={cn('capitalize', badgeColor)}>
              {tStatus(`status.${row.getValue('status')}`)}
            </Badge>
          </div>
        )
      },
      enableSorting: false,
      enableHiding: false,
    },
    {
      accessorKey: 'payment_status',
      header: ({ column }) => (
        <DataTableColumnHeader
          column={column}
          title={tLabel('payment_status')}
        />
      ),
      cell: ({ row }) => {
        const { payment_status } = row.original
        const badgeColor = paymentStatusTypes.get(payment_status)
        return (
          <div className='flex space-x-2'>
            <Badge variant='outline' className={cn('capitalize', badgeColor)}>
              {tStatus(`payment_status.${row.getValue('payment_status')}`)}
            </Badge>
          </div>
        )
      },
      enableSorting: false,
      enableHiding: false,
    },
    {
      accessorKey: 'total_amount',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('total_amount')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap'>
          {formatPrice(row.getValue('total_amount'), row.original.currency_id)}
        </div>
      ),
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'created_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('created_at')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap'>
          {formatDateTime(row.getValue('created_at'))}
        </div>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'updated_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('updated_at')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap'>
          {formatDateTime(row.getValue('updated_at'))}
        </div>
      ),
      enableHiding: true,
      enableSorting: false,
    },
    {
      id: 'actions',
      cell: DataTableRowActions,
    },
  ]
}
