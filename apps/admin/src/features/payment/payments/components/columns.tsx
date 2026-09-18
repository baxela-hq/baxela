import { Link } from '@tanstack/react-router'
import { type ColumnDef } from '@tanstack/react-table'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { methodTypes, statusTypes } from '../data/data'
import { Locales } from '../data/routes'
import { type Payment } from '../data/schema'
import { DataTableRowActions } from './data-table-row-actions'

export const Columns = (): ColumnDef<Payment>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.PAYMENT)
  const { formatDateTime } = useFormatDateTime()

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
      enableSorting: true,
      enableHiding: false,
    },
    {
      accessorKey: 'order_id',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('order_id')} />
      ),
      cell: ({ row }) => (
        <Link
          to='/order/orders/$id/show'
          params={{ id: row.original.order_id.toString() }}
          className='w-fit ps-2 font-medium underline-offset-4 hover:underline'
        >
          <span>#{row.getValue('order_id')}</span>
        </Link>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'transaction_id',
      header: ({ column }) => (
        <DataTableColumnHeader
          column={column}
          title={tLabel('transaction_id')}
        />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap'>
          {row.getValue<string | null>('transaction_id') ?? '—'}
        </div>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'method',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('method')} />
      ),
      cell: ({ row }) => {
        const method = row.getValue<string>('method')
        return (
          <div className='w-fit ps-2'>
            <Badge variant='outline' className={cn(methodTypes.get(method))}>
              {tStatus(`method.${method}`)}
            </Badge>
          </div>
        )
      },
      filterFn: (row, id, value) => {
        return value.includes(String(row.getValue(id)))
      },
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'amount',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('amount')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('amount')}</div>
      ),
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'status',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('status')} />
      ),
      cell: ({ row }) => {
        const status = row.getValue<string>('status')
        return (
          <div className='w-fit ps-2'>
            <Badge variant='outline' className={cn(statusTypes.get(status))}>
              {tStatus(`status.${status}`)}
            </Badge>
          </div>
        )
      },
      filterFn: (row, id, value) => {
        return value.includes(String(row.getValue(id)))
      },
      enableSorting: false,
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
      enableSorting: true,
      enableHiding: true,
    },
    {
      id: 'actions',
      cell: DataTableRowActions,
    },
  ]
}
