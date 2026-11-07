import { useState } from 'react'
import {
  type VisibilityState,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table'
import { cn } from '@/lib/utils'
import { type NavigateFn, useTableUrlState } from '@/hooks/use-table-url-state'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { DataTablePagination } from '@/components/data-table'
import { type FailedJob } from '../data/schema'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'
import { Columns } from './columns'
import { ViewDialog } from './view-dialog'
import { ForgetDialog, RetryDialog } from './confirm-dialogs'

type DataTableProps = {
  data: PaginatedResponse<FailedJob>
  search: Record<string, unknown>
  navigate: NavigateFn
}

export function DataTable({ data, search, navigate }: DataTableProps) {
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({})
  const [viewedUuid, setViewedUuid] = useState<string | null>(null)
  const [retryJob, setRetryJob] = useState<FailedJob | null>(null)
  const [forgetJob, setForgetJob] = useState<FailedJob | null>(null)

  const { tLabel } = useAppTranslation(Locales.FAILED_JOBS)
  const columns = Columns({
    onView: (job) => setViewedUuid(job.uuid),
    onRetry: (job) => setRetryJob(job),
    onForget: (job) => setForgetJob(job),
  })

  // Synced with URL states (pagination + sort mirror the route search keys)
  const {
    pagination,
    onPaginationChange,
    sorting,
    onSortingChange,
  } = useTableUrlState({
    search,
    navigate,
    pagination: { defaultPage: 1, defaultPageSize: 10 },
    globalFilter: { enabled: false },
    columnFilters: [],
    sorting: { key: 'sort' },
  })

  const table = useReactTable({
    data: data.data,
    columns,
    pageCount: data.meta.last_page,
    manualPagination: true,
    manualFiltering: true,
    manualSorting: true,
    state: {
      sorting,
      pagination,
      columnVisibility,
    },
    onPaginationChange,
    onSortingChange,
    onColumnVisibilityChange: setColumnVisibility,
    getCoreRowModel: getCoreRowModel(),
  })

  return (
    <div
      className={cn(
        'max-sm:has-[div[role="toolbar"]]:mb-16',
        'flex flex-1 flex-col gap-4'
      )}
    >
      <div className='overflow-hidden rounded-md border'>
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id} className='group/row'>
                {headerGroup.headers.map((header) => {
                  return (
                    <TableHead
                      key={header.id}
                      colSpan={header.colSpan}
                      className={cn(
                        'bg-background group-hover/row:bg-muted group-data-[state=selected]/row:bg-muted',
                        header.column.columnDef.meta?.className,
                        header.column.columnDef.meta?.thClassName
                      )}
                    >
                      {header.isPlaceholder
                        ? null
                        : flexRender(
                            header.column.columnDef.header,
                            header.getContext()
                          )}
                    </TableHead>
                  )
                })}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id} className='group/row'>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell
                      key={cell.id}
                      className={cn(
                        'bg-background group-hover/row:bg-muted',
                        cell.column.columnDef.meta?.className,
                        cell.column.columnDef.meta?.tdClassName
                      )}
                    >
                      {flexRender(
                        cell.column.columnDef.cell,
                        cell.getContext()
                      )}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell
                  colSpan={columns.length}
                  className='h-24 text-center'
                >
                  {tLabel('no-results')}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} className='mt-auto' />

      <ViewDialog uuid={viewedUuid} onClose={() => setViewedUuid(null)} />
      <RetryDialog job={retryJob} onClose={() => setRetryJob(null)} />
      <ForgetDialog job={forgetJob} onClose={() => setForgetJob(null)} />
    </div>
  )
}
