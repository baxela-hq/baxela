import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { Badge } from '@/components/ui/badge'
import { pickTranslation } from '@/shared/lib/locale'
import { Locales } from '../data/routes'
import { type InventoryStock } from '../data/schema';
import { DataTableRowActions } from './data-table-row-actions';
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'

// Mirrors the backend `inventory.low_stock_threshold` config default; the
// badge is display-only — actual filtering is server-side (filter[low_stock]).
const LOW_STOCK_THRESHOLD = 5

export const Columns = (): ColumnDef<InventoryStock>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.INVENTORY_STOCK)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const { formatDateTime } = useFormatDateTime()

  return [
    {
      id: 'select',
      header: ({ table }) => (
        <Checkbox
          checked={
            table.getIsAllPageRowsSelected() ||
            (table.getIsSomePageRowsSelected() && 'indeterminate')
          }
          onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
          aria-label={t('columns.aria-select-all')}
          className='translate-y-[2px]'
        />
      ),
      meta: {
        className: cn('max-md:sticky start-0 z-10 rounded-tl-[inherit]'),
      },
      cell: ({ row }) => (
        <Checkbox
          checked={row.getIsSelected()}
          onCheckedChange={(value) => row.toggleSelected(!!value)}
          aria-label={t('columns.aria-select-row')}
          className='translate-y-[2px]'
        />
      ),
      enableSorting: false,
      enableHiding: false,
    },
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
      id: 'product',
      accessorFn: (row) =>
        pickTranslation(row.variant?.product?.translations ?? [])?.title ?? '',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('product')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-36 ps-3'>
          {pickTranslation(row.original.variant?.product?.translations ?? [])?.title ||
            '—'}
        </LongText>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      id: 'variant',
      accessorFn: (row) => row.variant?.sku ?? '',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('variant')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-36 ps-3'>{row.original.variant?.sku || '—'}</LongText>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'quantity',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('quantity')} />
      ),
      cell: ({ row }) => {
        const quantity = row.getValue<number>('quantity')
        const isLowStock = quantity <= LOW_STOCK_THRESHOLD
        return (
          <div className='w-fit ps-2'>
            {isLowStock ? (
              <Badge variant='destructive'>
                {quantity} — {tStatus('low_stock')}
              </Badge>
            ) : (
              <span className='text-nowrap'>{quantity}</span>
            )}
          </div>
        )
      },
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'created_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('created_at')} />
      ),
      cell: ({ row }) =>
        row.getValue('created_at') ? (
          <div className='w-fit ps-2 text-nowrap'>{formatDateTime(row.getValue('created_at'))}</div>
        ) : (
          '—'
        ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'updated_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('updated_at')} />
      ),
      cell: ({ row }) =>
        row.getValue('updated_at') ? (
          <div className='w-fit ps-2 text-nowrap'>{formatDateTime(row.getValue('updated_at'))}</div>
        ) : (
          '—'
        ),
      enableHiding: true,
      enableSorting: false,
    },
    {
      id: 'actions',
      cell: DataTableRowActions,
    },
  ];
}
