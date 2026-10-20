import React from 'react'
import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { CheckCircle2, PackageCheck, Trash2, Undo2, XCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { statusTransitions, type OrderReturn, type ReturnStatus } from '../data/schema'
import { useReturns } from './provider.tsx'
import { Locales } from '../data/routes'
import { useAppTranslation } from '@/hooks/useAppTranslation'

type DataTableRowActionsProps = {
  row: Row<OrderReturn>
}

const TRANSITION_ICONS: Partial<Record<ReturnStatus, React.ReactNode>> = {
  approved: <CheckCircle2 size={16} />,
  rejected: <XCircle size={16} />,
  received: <PackageCheck size={16} />,
  cancelled: <Undo2 size={16} />,
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = useReturns()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { t } = useAppTranslation(Locales.RETURN)

  const nextStatuses = statusTransitions[row.original.status] ?? []

  return (
    <>
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button
            variant='ghost'
            className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
          >
            <DotsHorizontalIcon className='h-4 w-4' />
            <span className='sr-only'>Open menu</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align='end' className='w-[180px]'>
          {nextStatuses.map((status) => (
            <DropdownMenuItem
              key={status}
              onClick={() => {
                setCurrentRow(row.original)
                setOpen(status)
              }}
            >
              {t(`form.statuses.status.${status}`)}
              {TRANSITION_ICONS[status]}
            </DropdownMenuItem>
          ))}
          <DropdownMenuSeparator />
          <DropdownMenuItem
            onClick={() => {
              setCurrentRow(row.original)
              setOpen('delete')
            }}
            className='text-red-500!'
          >
            {tAction('delete')}
            <Trash2 size={16} className='ms-auto' />
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  )
}
