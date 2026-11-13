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
import { DataTablePagination, DataTableToolbar } from '@/components/data-table'
import { type CreditNote } from '../data/schema'
import { CreditNoteColumns } from './credit-note-columns'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'

/**
 * The credit-notes table (gap-B1 sliver) — the refund half of the
 * document chain, sharing the invoices page's URL state: a number
 * search or page applies to whichever table is showing, so looking up
 * "what happened around INV-2026-…" works from either side.
 */
type CreditNoteDataTableProps = {
  data: PaginatedResponse<CreditNote>
  search: Record<string, unknown>
  navigate: NavigateFn
}

export function CreditNoteDataTable({ data, search, navigate }: CreditNoteDataTableProps) {
  const [rowSelection, setRowSelection] = useState({})
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({})

  const { tLabel } = useAppTranslation(Locales.INVOICE)
  const { t: tDataTable } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const columns = CreditNoteColumns()

  const {
    columnFilters,
    onColumnFiltersChange,
    pagination,
    onPaginationChange,
    sorting,
    onSortingChange,
  } = useTableUrlState({
    search,
    navigate,
    pagination: { defaultPage: 1, defaultPageSize: 10 },
    globalFilter: { enabled: false },
    columnFilters: [
      { columnId: 'number', searchKey: 'filter[number]', type: 'string' },
    ],
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
      rowSelection,
      columnFilters,
      columnVisibility,
    },
    onPaginationChange,
    onColumnFiltersChange,
    onSortingChange,
    onRowSelectionChange: setRowSelection,
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
      <DataTableToolbar
        tLabel={tLabel}
        table={table}
        searchPlaceholder={tDataTable('toolbar.filter', { name: tLabel('number') })}
        searchKey='number'
        filters={[]}
      />
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
                <TableRow
                  key={row.id}
                  data-state={row.getIsSelected() && 'selected'}
                  className={cn(
                    'group/row hover:bg-muted data-[state=selected]:bg-muted',
                    row.original.status === 'void' && 'opacity-60'
                  )}
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-24 text-center">
                  {tDataTable('no_results')}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} />
    </div>
  )
}
