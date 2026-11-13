import { type ColumnDef } from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { CreditNoteRowActions } from './credit-note-row-actions'
import { type CreditNote } from '../data/schema'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'
import { pdfStatusBadgeVariants, statusBadgeVariants } from './columns'

/**
 * Credit-note columns (gap-B1 sliver): the refund-side document chain.
 * Mirrors the invoice table — number, its invoice, refund reason,
 * amount, status + PDF state — over the tested admin CN endpoints.
 */
export function CreditNoteColumns(): ColumnDef<CreditNote>[] {
  const { tLabel } = useAppTranslation(Locales.INVOICE)

  return [
    {
      accessorKey: 'number',
      header: tLabel('number'),
      cell: ({ row }) => (
        <span className="font-mono text-sm font-medium">{row.getValue('number')}</span>
      ),
    },
    {
      accessorKey: 'invoice_id',
      header: tLabel('invoice'),
      cell: ({ row }) => (
        <span className="text-muted-foreground">#{row.getValue('invoice_id')}</span>
      ),
    },
    {
      accessorKey: 'reason',
      header: tLabel('reason'),
      cell: ({ row }) => {
        const reason = row.getValue<string>('reason')
        return reason ? (
          <span
            className="block max-w-48 truncate text-muted-foreground"
            title={reason}
          >
            {reason}
          </span>
        ) : null
      },
    },
    {
      accessorKey: 'amount',
      header: tLabel('amount'),
      cell: ({ row }) => {
        const amount = row.getValue<string>('amount')
        return (
          <span className="font-medium">
            {amount} {row.original.currency_code}
          </span>
        )
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
      cell: ({ row }) => <CreditNoteRowActions row={row} />,
    },
  ]
}
