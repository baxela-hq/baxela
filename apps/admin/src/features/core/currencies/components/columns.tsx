import { type ColumnDef } from '@tanstack/react-table'
import { Pencil } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { DataTableColumnHeader } from '@/components/data-table'
import { useUpdateCurrency } from '../hooks/use-currency-mutations'
import { Locales } from '../data/routes'
import { defaultTypes } from '../data/data'
import { type CurrencyRow } from '../data/schema'
import { useCurrenciesDialog } from './provider'

export const Columns = (): ColumnDef<CurrencyRow>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.CURRENCY)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { setOpen, setCurrentRow } = useCurrenciesDialog()
  const updateCurrency = useUpdateCurrency()

  return [
    {
      accessorKey: 'code',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('code')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-3 font-medium'>
          {row.original.code}
          <span className='ms-1.5 text-muted-foreground'>
            {row.original.symbol}
          </span>
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
        <div className='w-fit ps-3'>
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
      accessorKey: 'exchange_rate',
      header: ({ column }) => (
        <DataTableColumnHeader
          column={column}
          title={tLabel('exchange-rate')}
        />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-3 font-mono'>{row.original.exchange_rate}</div>
      ),
      enableSorting: true,
      enableHiding: false,
    },
    {
      accessorKey: 'decimal_places',
      header: ({ column }) => (
        <DataTableColumnHeader
          column={column}
          title={tLabel('decimal-places')}
        />
      ),
      cell: ({ row }) => <div className='w-fit ps-3'>{row.original.decimal_places}</div>,
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'is_default',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('is-default')} />
      ),
      cell: ({ row }) => {
        const isDefault = row.original.is_default
        const badgeColor = defaultTypes.get(isDefault)
        return (
          <div className='w-fit ps-2'>
            <Badge variant='outline' className={cn(badgeColor)}>
              {tStatus('is-default.true')}
            </Badge>
          </div>
        )
      },
      enableSorting: false,
      enableHiding: true,
    },
    {
      accessorKey: 'is_active',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('is-active')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-2'>
          <Switch
            checked={row.original.is_active}
            // The base currency defines the rate scale — it stays active
            // (the backend strips the flag anyway).
            disabled={row.original.is_default || updateCurrency.isPending}
            onCheckedChange={(checked) =>
              updateCurrency.mutate({
                id: row.original.id,
                data: { is_active: checked },
              })
            }
            aria-label={tLabel('is-active')}
          />
        </div>
      ),
      enableSorting: false,
      enableHiding: true,
    },
    {
      id: 'actions',
      cell: ({ row }) => (
        <Button
          variant='ghost'
          className='flex h-8 w-8 p-0'
          onClick={() => {
            setCurrentRow(row.original)
            setOpen('edit')
          }}
        >
          <Pencil className='size-4' />
          <span className='sr-only'>{tAction('edit')}</span>
        </Button>
      ),
    },
  ]
}
