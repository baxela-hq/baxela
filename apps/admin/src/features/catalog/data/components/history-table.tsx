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
import { useCatalogImportsList } from '../hooks/use-data-transfer'
import { Locales } from '../data/routes'

export function HistoryTable() {
  const { tLabel, tStatus } = useAppTranslation(Locales.DATA)
  const historyQuery = useCatalogImportsList()
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
              <TableCell className='font-medium'>
                {row.filename}
                {row.dry_run && (
                  <Badge variant='outline' className='ms-2'>
                    {tLabel('dry_run')}
                  </Badge>
                )}
              </TableCell>
              <TableCell>
                <Badge
                  variant={row.status === 'completed' ? 'secondary' : 'destructive'}
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
                <TableCell colSpan={7}>
                  <ul className='flex flex-col gap-1'>
                    {(row.errors ?? []).map((error, index) => (
                      <li
                        key={`${error.section}-${error.row}-${index}`}
                        className='text-sm text-destructive'
                      >
                        [{error.section} #{error.row}]{' '}
                        {error.messages.join(' ')}
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
