import { Fragment, useState } from 'react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Locales } from '../data/routes'
import { useTransferImportsList } from '../hooks/use-transfer'

/**
 * Run history across all modules, latest first. Rows with errors expand
 * in place to show the per-row failure strings the module transfer
 * implementations recorded.
 */
export function HistoryTable() {
  const { tLabel, tStatus } = useAppTranslation(Locales.TRANSFER)
  const historyQuery = useTransferImportsList()
  const [expandedRunId, setExpandedRunId] = useState<number | null>(null)

  if (historyQuery.isLoading) {
    return <Skeleton className='h-24 w-full' />
  }

  if ((historyQuery.data?.data ?? []).length === 0) {
    return (
      <p className='text-sm text-muted-foreground'>{tLabel('no_history')}</p>
    )
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{tLabel('module')}</TableHead>
          <TableHead>{tLabel('filename')}</TableHead>
          <TableHead>{tLabel('status')}</TableHead>
          <TableHead>{tLabel('created')}</TableHead>
          <TableHead>{tLabel('updated')}</TableHead>
          <TableHead>{tLabel('skipped')}</TableHead>
          <TableHead>{tLabel('failed')}</TableHead>
          <TableHead>{tLabel('run_at')}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {(historyQuery.data?.data ?? []).map((row) => (
          <Fragment key={row.id}>
            <TableRow
              className={row.errors?.length ? 'cursor-pointer' : undefined}
              onClick={() =>
                row.errors?.length &&
                setExpandedRunId(expandedRunId === row.id ? null : row.id)
              }
            >
              <TableCell className='font-medium capitalize'>
                {row.module}
              </TableCell>
              <TableCell>
                {row.filename}
                {row.dry_run && (
                  <Badge variant='outline' className='ms-2'>
                    {tLabel('dry_run')}
                  </Badge>
                )}
              </TableCell>
              <TableCell>
                <Badge
                  variant={
                    row.status === 'completed' ? 'secondary' : 'destructive'
                  }
                >
                  {tStatus(`status.${row.status}`)}
                </Badge>
              </TableCell>
              <TableCell>{row.created_count}</TableCell>
              <TableCell>{row.updated_count}</TableCell>
              <TableCell>{row.skipped_count}</TableCell>
              <TableCell>{row.failed_count}</TableCell>
              <TableCell className='text-muted-foreground'>
                {row.created_at ?? '—'}
              </TableCell>
            </TableRow>
            {expandedRunId === row.id && (row.errors ?? []).length > 0 && (
              <TableRow>
                <TableCell colSpan={8}>
                  <ul className='flex flex-col gap-1'>
                    {(row.errors ?? []).map((error, index) => (
                      <li
                        key={`${row.id}-${index}`}
                        className='text-sm text-destructive'
                      >
                        {error}
                      </li>
                    ))}
                  </ul>
                </TableCell>
              </TableRow>
            )}
          </Fragment>
        ))}
      </TableBody>
    </Table>
  )
}
