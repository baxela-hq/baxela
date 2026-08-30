import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { getDefaultCurrency } from '@/shared/lib/locale'
import { Locales } from '../data/routes'
import { type Rate } from '../data/schema';
import { DataTableRowActions } from './data-table-row-actions';

export const Columns = (): ColumnDef<Rate>[] => {
  const { tLabel } = useAppTranslation(Locales.RATE)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)

  const currency = getDefaultCurrency()
  const symbol = currency?.symbol ?? '$'
  const formatPrice = (price: string) =>
    currency?.is_symbol_right ? `${price} ${symbol}` : `${symbol}${price}`

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
      enableHiding: false,
      enableSorting: true,
    },
    {
      accessorKey: 'method_id',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('method_id')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('method_id')}</div>,
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'zone_id',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('zone_id')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('zone_id')}</div>,
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'price',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('price')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{formatPrice(row.getValue('price'))}</div>,
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'created_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('created_at')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('created_at')}</div>,
      enableSorting: false,
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
