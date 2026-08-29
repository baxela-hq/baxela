import { useMemo, useState } from 'react'
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
import { DATA_TYPES, type Attribute } from '../data/schema.ts'
import { DataTableBulkActions } from './data-table-bulk-actions.tsx'
import { Columns } from './columns.tsx'
import { useAttributeGroupOptions } from '../../attribute-groups/hooks/use-attribute-groups'
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Locales } from '../data/routes.ts';
import { type PaginatedResponse } from '@/shared/types/common.types'
import { getDefaultLanguage } from '@/shared/lib/locale.ts'

type DataTableProps = {
  data: PaginatedResponse<Attribute>,
  search: Record<string, unknown>
  navigate: NavigateFn
}

export function DataTable({ data, search, navigate }: DataTableProps) {
  // Local UI-only states
  const [rowSelection, setRowSelection] = useState({})
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({
    updated_at: false,
  })

  const { tLabel, tStatus } = useAppTranslation(Locales.ATTRIBUTE)
  const { tLabel: tcLabel } = useAppTranslation(Locales.SHARED_COMMON)
  const { t: tDataTable } = useAppTranslation(Locales.SHARED_DATA_TABLE)

  const groups = useAttributeGroupOptions()

  const groupNames = useMemo(() => {
    const names: Record<number, string> = {}
    for (const group of groups) {
      const index = getDefaultLanguage(group.translations) ?? 0
      names[group.id] = group.translations[index]?.title ?? String(group.id)
    }
    return names
  }, [groups])

  const columns = Columns(groupNames);

  // Synced with URL states
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
      { columnId: 'title', searchKey: 'filter[title]', type: 'string' },
      { columnId: 'data_type', searchKey: 'filter[data_type]', type: 'array' },
      { columnId: 'is_filterable', searchKey: 'filter[is_filterable]', type: 'array' },
      { columnId: 'group_id', searchKey: 'filter[group_id]', type: 'array' },
    ],
    sorting: { key: 'sort' },
  })

  const table= useReactTable({
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
        'max-sm:has-[div[role="toolbar"]]:mb-16', // Add margin bottom to the table on mobile when the toolbar is visible
        'flex flex-1 flex-col gap-4'
      )}
    >
      <DataTableToolbar
        tLabel={tLabel}
        table={table}
        searchPlaceholder={tDataTable('toolbar.filter', {name: tLabel('title')})}
        searchKey='title'
        filters={[
          {
            columnId: 'group_id',
            title: tLabel('group'),
            options: groups.map((group) => ({
              label: groupNames[group.id] ?? String(group.id),
              value: String(group.id),
            })),
          },
          {
            columnId: 'data_type',
            title: tLabel('data_type'),
            options: DATA_TYPES.map((dataType) => ({
              label: tStatus(`data_type.${dataType}`),
              value: dataType,
            })),
          },
          {
            columnId: 'is_filterable',
            title: tLabel('is_filterable'),
            options: [
              { label: tcLabel('yes'), value: 'true' },
              { label: tcLabel('no'), value: 'false' },
            ],
          },
        ]}
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
                  className='group/row'
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell
                      key={cell.id}
                      className={cn(
                        'bg-background group-hover/row:bg-muted group-data-[state=selected]/row:bg-muted',
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
      <DataTableBulkActions table={table} />
    </div>
  )
}
