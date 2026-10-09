import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { Locales } from '../data/routes'
import { type PostCategory, type TranslationForm } from '../data/schema';
import { DataTableRowActions } from './data-table-row-actions';
import { pickTranslation } from '@/shared/lib/locale.ts'
import { usePostCategoryTree } from '../hooks/use-post-categories'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'


export const Columns = (): ColumnDef<PostCategory>[] => {
  const { tLabel } = useAppTranslation(Locales.POST_CATEGORY)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const { formatDateTime } = useFormatDateTime()
  const categoryTree = usePostCategoryTree()
  const parentTitles = new Map(categoryTree.map((cat) => [cat.id, cat.title]))

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
      id: 'parent',
      accessorFn: (row) =>
        row.parent_id === null ? '' : parentTitles.get(row.parent_id) ?? '',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('parent_id')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-36 ps-3'>
          {row.original.parent_id === null
            ? tLabel('none')
            : parentTitles.get(row.original.parent_id) ?? '—'}
        </LongText>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      id: 'slug',
      accessorFn: (row) => pickTranslation(row.translations)?.slug ?? '',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('slug')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{pickTranslation(row.original.translations)?.slug}</div>,
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
      id: 'description',
      accessorFn: (row) => pickTranslation(row.translations)?.description ?? '',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('description')} />
      ),
      cell: ({ row }) =>
        <div className='w-fit ps-2 text-nowrap'>{pickTranslation(row.original.translations)?.description}</div>,
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
  ];
}
