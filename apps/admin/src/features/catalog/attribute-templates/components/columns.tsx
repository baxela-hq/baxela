import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { Locales } from '../data/routes'
import { activeTypes } from '../data/data'
import { type AttributeTemplate } from '../data/schema';
import { DataTableRowActions } from './data-table-row-actions';


export const Columns = (): ColumnDef<AttributeTemplate>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.ATTRIBUTE_TEMPLATE)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)

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
      accessorKey: 'title',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('title')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-36 ps-3'>{row.getValue('title')}</LongText>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'is_active',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('is_active')} />
      ),
      cell: ({ row }) => {
        const isActive = row.getValue<boolean>('is_active')
        const badgeColor = activeTypes.get(isActive)
        return (
          <div className='w-fit ps-2'>
            <Badge variant='outline' className={cn(badgeColor)}>
              {tStatus(`is_active.${isActive ? 'true' : 'false'}`)}
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
      accessorKey: 'position',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('position')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('position')}</div>,
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'groups_count',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('groups_count')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>
          {row.getValue<number | null | undefined>('groups_count') ?? '—'}
        </div>,
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'description',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('description')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('description') ?? '—'}</div>,
      enableSorting: false,
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
