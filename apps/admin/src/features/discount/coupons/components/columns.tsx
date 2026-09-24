import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { getDefaultCurrency } from '@/shared/lib/locale'
import { Locales } from '../data/routes'
import { type Coupon } from '../data/schema';
import { DataTableRowActions } from './data-table-row-actions';
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'

export const Columns = (): ColumnDef<Coupon>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.COUPON)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const { formatDateTime } = useFormatDateTime()

  const currency = getDefaultCurrency()
  const symbol = currency?.symbol ?? '$'
  const formatMoney = (amount: string) =>
    currency?.is_symbol_right ? `${amount} ${symbol}` : `${symbol}${amount}`

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
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'code',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('code')} />
      ),
      cell: ({ row }) => (
        <span className='max-w-36 ps-3 font-mono text-nowrap'>
          {row.getValue('code')}
        </span>
      ),
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'type',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('type')} />
      ),
      cell: ({ row }) => (
        <Badge variant={row.getValue('type') === 'percent' ? 'default' : 'secondary'}>
          {tStatus(row.getValue('type'))}
        </Badge>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'value',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('value')} />
      ),
      cell: ({ row }) => {
        const { value, type, max_discount_amount: cap } = row.original
        return (
          <div className='w-fit ps-2 text-nowrap'>
            {type === 'percent'
              ? `${value}%${cap ? ` (≤ ${formatMoney(cap)})` : ''}`
              : formatMoney(value)}
          </div>
        )
      },
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'usage_count',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('usage')} />
      ),
      cell: ({ row }) => {
        const { usage_count: used, usage_limit: limit } = row.original
        return (
          <div className='w-fit ps-2 text-nowrap'>
            {used} / {limit ?? '∞'}
          </div>
        )
      },
      enableSorting: true,
      enableHiding: true,
    },
    {
      id: 'window',
      accessorFn: (row) => row.starts_at ?? row.ends_at ?? '',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('window')} />
      ),
      cell: ({ row }) => {
        const { starts_at: start, ends_at: end } = row.original
        return (
          <div className='w-fit ps-2 text-nowrap text-muted-foreground'>
            {start || end
              ? `${start ? formatDateTime(start) : '∞'} → ${end ? formatDateTime(end) : '∞'}`
              : '∞'}
          </div>
        )
      },
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'is_active',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('is_active')} />
      ),
      cell: ({ row }) => (
        <Badge variant={row.getValue('is_active') ? 'default' : 'outline'}>
          {tStatus(row.getValue('is_active') ? 'active' : 'inactive')}
        </Badge>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'created_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('created_at')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{formatDateTime(row.getValue('created_at'))}</div>,
      enableSorting: false,
      enableHiding: true,
    },
    {
      id: 'actions',
      cell: DataTableRowActions,
    },
  ]
}
