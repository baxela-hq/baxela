import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Badge } from '@/components/ui/badge';
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { Locales } from '../data/routes'
import { statusColors } from '../data/data'
import { type Shipment } from '../data/schema';
import { DataTableRowActions } from './data-table-row-actions';

export const Columns = (): ColumnDef<Shipment>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.SHIPMENT)

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
          'ps-0.5 max-md:sticky start-0 @4xl/content:table-cell'
        ),
      },
      enableHiding: false,
      enableSorting: true,
    },
    {
      accessorKey: 'order_id',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('order_id')} />
      ),
      cell: ({ row }) =>
        <LongText className='max-w-36 ps-3'>{row.getValue('order_id')}</LongText>,
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'carrier_name',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('carrier_name')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('carrier_name') ?? '—'}</div>,
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'tracking_number',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('tracking_number')} />
      ),
      cell: ({ row }) => {
        const trackingNumber = row.getValue<string | null>('tracking_number')
        const trackingUrl = row.original.tracking_url
        if (!trackingNumber) {
          return <div className='w-fit ps-2 text-nowrap'>—</div>
        }
        return (
          <div className='w-fit ps-2 text-nowrap'>
            {trackingUrl ? (
              <a
                href={trackingUrl}
                target='_blank'
                rel='noreferrer'
                className='underline underline-offset-4 hover:text-primary'
              >
                {trackingNumber}
              </a>
            ) : (
              trackingNumber
            )}
          </div>
        )
      },
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'status',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('status')} />
      ),
      cell: ({ row }) => {
        const status = row.getValue<Shipment['status']>('status')
        const badgeColor = statusColors.get(status)
        return (
          <div className='w-fit ps-2'>
            <Badge variant='outline' className={cn(badgeColor)}>
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
      accessorKey: 'shipped_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('shipped_at')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('shipped_at') ?? '—'}</div>,
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'delivered_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('delivered_at')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('delivered_at') ?? '—'}</div>,
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'updated_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('updated_at')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('updated_at')}</div>,
      enableHiding: true,
      enableSorting: false,
    },
    {
      id: 'actions',
      cell: DataTableRowActions,
    },
  ];
}
