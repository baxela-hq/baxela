import { type ColumnDef } from '@tanstack/react-table';
import { cn } from '@/lib/utils';
import { DataTableColumnHeader } from '@/components/data-table';
import { LongText } from '@/components/long-text';
import { type Role } from '../data/schema';
import { DataTableRowActions } from './data-table-row-actions';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Locales } from '../data/routes';
import { Badge } from '@/components/ui/badge.tsx'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

export const Columns = (): ColumnDef<Role>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.ROLE)

  return [
    {
      accessorKey: 'id',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('id')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-16 ps-3'>{row.getValue('id')}</LongText>
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
      accessorKey: 'name',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('name')} />
      ),
      cell: ({ row }) => {
        const isSuperAdmin = row.original.name === 'super-admin'
        return (
          <div className='flex items-center gap-2'>
            <LongText className='max-w-48 ps-3 font-medium'>
              {row.getValue('name')}
            </LongText>
            {isSuperAdmin && (
              <Badge variant='outline'>{tStatus('system')}</Badge>
            )}
          </div>
        )
      },
      enableHiding: false,
      enableSorting: true,
    },
    {
      accessorKey: 'permissions',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('permissions')} />
      ),
      cell: ({ row }) => {
        const { permissions } = row.original
        const visible = permissions.slice(0, 3)
        const rest = permissions.length - visible.length
        if (permissions.length === 0) {
          return <span className='ps-2 text-muted-foreground'>—</span>
        }
        return (
          <div className='flex flex-wrap items-center gap-1'>
            {visible.map((permission) => (
              <Badge key={permission.id} variant='default'>
                {permission.name}
              </Badge>
            ))}
            {rest > 0 && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <Badge variant='secondary'>+{rest}</Badge>
                </TooltipTrigger>
                <TooltipContent className='max-w-72'>
                  <ul className='list-inside list-disc'>
                    {permissions.slice(3).map((permission) => (
                      <li key={permission.id}>{permission.name}</li>
                    ))}
                  </ul>
                </TooltipContent>
              </Tooltip>
            )}
          </div>
        )
      },
      filterFn: 'includesString',
      enableHiding: false,
      enableSorting: false,
    },
    {
      id: 'actions',
      cell: DataTableRowActions,
    },
  ];
}
