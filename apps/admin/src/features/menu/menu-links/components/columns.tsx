import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { Badge } from '@/components/ui/badge'
import { Locales } from '../data/routes'
import { type MenuLink, type TranslationForm } from '../data/schema';
import { DataTableRowActions } from './data-table-row-actions';
import { pickTranslation } from '@/shared/lib/locale.ts'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'
import { useMenuLinksAll } from '../hooks/use-menu-links'


export const Columns = (menuId: string): ColumnDef<MenuLink>[] => {
  const { tLabel } = useAppTranslation(Locales.MENU_LINK)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const { formatDateTime } = useFormatDateTime()
  const allLinks = useMenuLinksAll(menuId)

  const parentTitle = (parentId: number | null) => {
    if (parentId === null) return '—'
    const parent = allLinks.find((link) => link.id === parentId)
    return pickTranslation(parent?.translations ?? [])?.title || parentId.toString()
  }

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
      id: 'title',
      accessorKey: 'translations', // This defines the column
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('title')} />
      ),
      cell: ({ row }) => {
        const translations : TranslationForm[] = row.getValue('title');
        const title = pickTranslation(translations)?.title || '';
        return <LongText className='max-w-36 ps-3'>{title}</LongText>;
      },
      meta: {
        className: cn(
          'drop-shadow-[0_1px_2px_rgb(0_0_0_/_0.1)] dark:drop-shadow-[0_1px_2px_rgb(255_255_255_/_0.1)]',
          'ps-0.5 max-md:sticky start-6 @4xl/content:table-cell @4xl/content:drop-shadow-none'
        ),
      },
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'parent_id',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('parent')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2 text-nowrap'>
          {parentTitle(row.getValue('parent_id'))}
        </div>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'url',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('url')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{row.getValue('url')}</div>,
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'target',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('target')} />
      ),
      cell: ({ row }) => (
        <Badge variant='outline' className='ps-2'>
          {tLabel(`target_${row.getValue('target')}`)}
        </Badge>
      ),
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
      accessorKey: 'updated_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('updated_at')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{formatDateTime(row.getValue('updated_at'))}</div>,
      enableHiding: true,
      enableSorting: false,
    },
    {
      id: 'actions',
      cell: DataTableRowActions,
    },
  ];
}
