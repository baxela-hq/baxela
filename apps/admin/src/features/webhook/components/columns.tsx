import { type ColumnDef } from '@tanstack/react-table'
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { Badge } from '@/components/ui/badge'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time'
import { type WebhookEndpoint } from '../data/schema'
import { Locales } from '../data/routes'
import { RowActions } from './row-actions'

type ColumnsHandlers = {
  onDeliveries: (endpoint: WebhookEndpoint) => void
  onEdit: (endpoint: WebhookEndpoint) => void
  onTest: (endpoint: WebhookEndpoint) => void
  onRegenerate: (endpoint: WebhookEndpoint) => void
  onArchive: (endpoint: WebhookEndpoint) => void
}

export const Columns = ({
  onDeliveries,
  onEdit,
  onTest,
  onRegenerate,
  onArchive,
}: ColumnsHandlers): ColumnDef<WebhookEndpoint>[] => {
  const { tLabel, tStatus } = useAppTranslation(Locales.WEBHOOK)
  const { formatDateTime } = useFormatDateTime()

  return [
    {
      accessorKey: 'name',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('name')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-40 ps-3'>{row.getValue('name')}</LongText>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'url',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('url')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-64 font-mono text-xs'>
          {row.getValue('url')}
        </LongText>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'events',
      header: () => <div className='ps-3'>{tLabel('events')}</div>,
      cell: ({ row }) => (
        <div className='flex max-w-56 flex-wrap gap-1 ps-3'>
          {row.original.events.map((event) => (
            <Badge key={event} variant='outline' className='font-mono text-[10px]'>
              {event}
            </Badge>
          ))}
        </div>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'is_active',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('is_active')} />
      ),
      cell: ({ row }) => (
        <Badge variant={row.getValue('is_active') ? 'default' : 'secondary'}>
          {row.getValue('is_active')
            ? tStatus('active')
            : tStatus('inactive')}
        </Badge>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'consecutive_failures',
      header: ({ column }) => (
        <DataTableColumnHeader
          column={column}
          title={tLabel('consecutive_failures')}
        />
      ),
      cell: ({ row }) => {
        const failures = row.getValue<number>('consecutive_failures')
        return (
          <Badge variant={failures > 0 ? 'destructive' : 'secondary'}>
            {String(failures)}
          </Badge>
        )
      },
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'updated_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('updated_at')} />
      ),
      cell: ({ row }) => (
        <span className='ps-3 text-xs'>
          {formatDateTime(row.getValue('updated_at'))}
        </span>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      id: 'actions',
      cell: ({ row }) => (
        <RowActions
          endpoint={row.original}
          onDeliveries={() => onDeliveries(row.original)}
          onEdit={() => onEdit(row.original)}
          onTest={() => onTest(row.original)}
          onRegenerate={() => onRegenerate(row.original)}
          onArchive={() => onArchive(row.original)}
        />
      ),
    },
  ]
}
