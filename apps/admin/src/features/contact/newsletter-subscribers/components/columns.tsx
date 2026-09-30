import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { Badge } from '@/components/ui/badge'
import { Locales } from '../data/routes'
import { type NewsletterSubscriber } from '../data/schema';
import { statusBadgeVariants } from '../data/data';
import { DataTableRowActions } from './data-table-row-actions';
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'

export const Columns = (): ColumnDef<NewsletterSubscriber>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.NEWSLETTER_SUBSCRIBER)
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
      accessorKey: 'email',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('email')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-48 ps-3'>{row.getValue('email')}</LongText>
      ),
      enableSorting: false,
      enableHiding: false,
    },
    {
      accessorKey: 'locale',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('locale')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap uppercase'>{row.getValue('locale') || '—'}</div>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'status',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('status')} />
      ),
      cell: ({ row }) => {
        const { status } = row.original
        return (
          <div className='flex space-x-2'>
            <Badge variant='outline' className={cn(statusBadgeVariants.get(status))}>
              {tStatus(`status.${status}`)}
            </Badge>
          </div>
        )
      },
      filterFn: (row, id, value) => {
        return value.includes(row.getValue(id))
      },
      enableSorting: true,
      enableHiding: false,
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
  ];
}
