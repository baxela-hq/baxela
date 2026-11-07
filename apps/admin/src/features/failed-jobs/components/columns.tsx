import { type ColumnDef } from '@tanstack/react-table'
import { DataTableColumnHeader } from '@/components/data-table'
import { LongText } from '@/components/long-text'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time'
import { type FailedJob } from '../data/schema'
import { Locales } from '../data/routes'
import { RowActions } from './row-actions'

type ColumnsHandlers = {
  onView: (job: FailedJob) => void
  onRetry: (job: FailedJob) => void
  onForget: (job: FailedJob) => void
}

export const Columns = ({ onView, onRetry, onForget }: ColumnsHandlers): ColumnDef<FailedJob>[] => {
  const { tLabel } = useAppTranslation(Locales.FAILED_JOBS)
  const { formatDateTime } = useFormatDateTime()

  return [
    {
      accessorKey: 'uuid',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('uuid')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-40 ps-3'>{row.getValue('uuid')}</LongText>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'job',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('job')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-72 ps-3'>
          {row.getValue('job') ?? '—'}
        </LongText>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'queue',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('queue')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-3 text-nowrap'>{row.getValue('queue')}</div>
      ),
      enableHiding: false,
      enableSorting: false,
    },
    {
      accessorKey: 'connection',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('connection')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-3 text-nowrap'>{row.getValue('connection')}</div>
      ),
      enableHiding: true,
      enableSorting: false,
    },
    {
      accessorKey: 'failed_at',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('failed-at')} />
      ),
      cell: ({ row }) => (
        <div className='w-fit ps-3 text-nowrap'>
          {row.original.failed_at ? formatDateTime(row.original.failed_at) : '—'}
        </div>
      ),
      enableHiding: false,
      enableSorting: true,
    },
    {
      accessorKey: 'exception',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('exception')} />
      ),
      cell: ({ row }) => (
        <LongText className='max-w-80 ps-3'>
          {row.getValue('exception')}
        </LongText>
      ),
      enableHiding: true,
      enableSorting: false,
    },
    {
      id: 'actions',
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tLabel('actions')} />
      ),
      cell: ({ row }) => (
        <RowActions
          onView={() => onView(row.original)}
          onRetry={() => onRetry(row.original)}
          onForget={() => onForget(row.original)}
        />
      ),
      enableHiding: false,
      enableSorting: false,
    },
  ]
}
