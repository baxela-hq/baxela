import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { DataTableRowActions } from './data-table-row-actions'
import { type Invoice } from '../data/schema'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'

export const statusBadgeVariants = new Map<string, string>([
  ['active', 'bg-emerald-100/30 text-emerald-900 dark:text-emerald-200 border-emerald-200'],
  ['void', 'bg-neutral-300/40 border-neutral-300 text-neutral-600'],
])

export const pdfStatusBadgeVariants = new Map<string, string>([
  ['ready', 'bg-emerald-100/30 text-emerald-900 dark:text-emerald-200 border-emerald-200'],
  ['pending_generation', 'bg-amber-100/30 text-amber-900 dark:text-amber-200 border-amber-200'],
  ['generating', 'bg-sky-100/30 text-sky-900 dark:text-sky-200 border-sky-200'],
  ['generation_failed', 'bg-rose-100/30 text-rose-900 dark:text-rose-200 border-rose-200'],
])

export function Columns(): ColumnDef<Invoice>[] {
  const { tLabel } = useAppTranslation(Locales.INVOICE)

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
          aria-label="Select all"
        />
      ),
      cell: ({ row }) => (
        <Checkbox
          checked={row.getIsSelected()}
          onCheckedChange={(value) => row.toggleSelected(!!value)}
          aria-label="Select row"
        />
      ),
      enableSorting: false,
      enableHiding: false,
    },
    {
      accessorKey: 'number',
      header: tLabel('number'),
      cell: ({ row }) => (
        <span className="font-mono text-sm font-medium">{row.getValue('number')}</span>
      ),
    },
    {
      accessorKey: 'order_id',
      header: tLabel('order'),
      cell: ({ row }) => (
        <span className="text-muted-foreground">#{row.getValue('order_id')}</span>
      ),
    },
    {
      accessorKey: 'revision',
      header: tLabel('revision'),
      cell: ({ row }) => (
        <span className="text-muted-foreground">r{row.getValue('revision')}</span>
      ),
    },
    {
      accessorKey: 'total_amount',
      header: tLabel('total'),
      cell: ({ row }) => {
        const total = row.getValue<string>('total_amount') ?? '0'
        const currency = row.original.currency_code
        return (
          <span className="font-medium">
            {total} {currency}
          </span>
        )
      },
    },
    {
      accessorKey: 'credited_amount',
      header: tLabel('credited'),
      cell: ({ row }) => {
        const credited = row.getValue<string>('credited_amount')
        if (credited === '0.00' || credited === '0') return null
        return <span className="text-amber-600 dark:text-amber-400">-{credited}</span>
      },
    },
    {
      accessorKey: 'balance',
      header: tLabel('balance'),
      cell: ({ row }) => {
        const balance = row.getValue<string>('balance')
        return <span className="font-medium">{balance}</span>
      },
    },
    {
      accessorKey: 'status',
      header: tLabel('status'),
      cell: ({ row }) => {
        const status = row.getValue<string>('status')
        return (
          <Badge variant="outline" className={statusBadgeVariants.get(status) ?? ''}>
            {status}
          </Badge>
        )
      },
    },
    {
      accessorKey: 'pdf_status',
      header: tLabel('pdf'),
      cell: ({ row }) => {
        const status = row.getValue<string>('pdf_status')
        return (
          <Badge variant="outline" className={pdfStatusBadgeVariants.get(status) ?? ''}>
            {status === 'ready' ? '✓' : status === 'generation_failed' ? '✗' : '…'}
          </Badge>
        )
      },
    },
    {
      accessorKey: 'issued_at',
      header: tLabel('issued_at'),
      cell: ({ row }) => {
        const date = row.getValue<string>('issued_at')
        return date ? (
          <span className="text-muted-foreground">{new Date(date).toLocaleDateString()}</span>
        ) : null
      },
    },
    {
      id: 'actions',
      cell: ({ row }) => <DataTableRowActions row={row} />,
    },
  ]
}
