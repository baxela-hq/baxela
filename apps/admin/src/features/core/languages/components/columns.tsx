import { type ColumnDef } from '@tanstack/react-table'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { DataTableColumnHeader } from '@/components/data-table'
import { useUpdateLanguage } from '../hooks/use-language-mutations'
import { Locales } from '../data/routes'
import type { Language } from '@/shared/types/locale.types'

export const Columns = (): ColumnDef<Language>[] => {
  const { tLabel, tStatus, tHelpText } = useAppTranslation(Locales.LANGUAGE)
  const updateLanguage = useUpdateLanguage()

  return [
    {
      accessorKey: 'code',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('code')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-3 font-medium'>
          {row.original.code}
          {row.original.is_rtl && (
            <Badge variant='outline' className='ms-1.5'>
              {tStatus('rtl')}
            </Badge>
          )}
        </div>
      ),
      enableSorting: true,
      enableHiding: false,
    },
    {
      accessorKey: 'name',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('name')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit'>
          <div>{row.original.name}</div>
          {row.original.native_name &&
            row.original.native_name !== row.original.name && (
              <div className='text-muted-foreground'>
                {row.original.native_name}
              </div>
            )}
        </div>
      ),
      enableSorting: true,
      enableHiding: false,
    },
    {
      accessorKey: 'is_default',
      header: () => <div className='text-start'>{tLabel('default')}</div>,
      cell: ({ row }) =>
        row.original.is_default ? (
          <Badge variant='default'>{tStatus('is-default')}</Badge>
        ) : (
          <span className='text-muted-foreground'>—</span>
        ),
      enableSorting: true,
      enableHiding: false,
    },
    {
      accessorKey: 'is_active',
      header: () => <div className='text-end pe-3'>{tLabel('active')}</div>,
      cell: ({ row }) => (
        <div className='flex justify-end pe-3'>
          <Switch
            checked={row.original.is_active}
            title={
              !row.original.is_implemented && !row.original.is_active
                ? tHelpText('not-implemented')
                : row.original.is_default
                  ? tHelpText('default-lock')
                  : undefined
            }
            disabled={
              row.original.is_default ||
              (!row.original.is_implemented && !row.original.is_active) ||
              updateLanguage.isPending
            }
            onCheckedChange={(checked) =>
              updateLanguage.mutate({
                id: row.original.id,
                data: { is_active: checked },
              })
            }
          />
        </div>
      ),
      enableSorting: true,
      enableHiding: false,
    },
  ]
}
