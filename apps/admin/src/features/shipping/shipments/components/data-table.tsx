import {
  type VisibilityState,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table'
import { useState } from 'react'
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
import { type Shipment } from '../data/schema'
import { Columns } from './columns.tsx'
import { type PaginatedResponse } from '@/shared/types/common.types'
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Locales } from '../data/routes';

type DataTableProps = {
  data: PaginatedResponse<Shipment>,
  search: Record<string, unknown>
  navigate: NavigateFn
}

export function DataTable({ data, search, navigate }: DataTableProps) {
  // Local UI-only states (no row selection — shipments cannot be deleted in bulk)
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({})

  const { tLabel } = useAppTranslation(Locales.SHIPMENT)
  const { t: tDataTable } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const columns = Columns();

  // Synced with URL states (keys/defaults mirror sibling module routes)
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
      { columnId: 'order_id', searchKey: 'filter[order_id]', type: 'string' },
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
      columnFilters,
      columnVisibility,
    },
    onPaginationChange,
    onColumnFiltersChange,
    onSortingChange,
    onColumnVisibilityChange: setColumnVisibility,
    getCoreRowModel: getCoreRowModel(),
  })

  return (
    <div
      className={cn(
        'flex flex-1 flex-col gap-4'
      )}
    >
      <DataTableToolbar
        tLabel={tLabel}
        table={table}
        searchPlaceholder={tDataTable('toolbar.filter', {name: tLabel('order_id')})}
        searchKey='order_id'
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
                  className='group/row'
                >
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
                  No results.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} className='mt-auto' />
    </div>
  )
}
