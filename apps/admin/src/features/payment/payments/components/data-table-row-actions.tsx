import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { BadgeCheck, History, RotateCcw, XCircle } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Locales } from '../data/routes'
import { isSettleable, type Payment } from '../data/schema'
import { usePayments } from './provider.tsx'

type DataTableRowActionsProps = {
  row: Row<Payment>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = usePayments()
  const { tAction, tTooltip } = useAppTranslation(Locales.PAYMENT)
  const settleable = isSettleable(row.original.status)
  const refundable = row.original.status === 'success'
  // Issuing needs both a successful payment and a driver with refunds wired;
  // history stays reachable for any successful payment.
  const canIssueRefund = refundable && row.original.is_refundable
  const refundTooltip = refundable && !canIssueRefund
    ? tTooltip('refund_unsupported_driver')
    : undefined

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
        <DropdownMenuContent align='end' className='w-[160px]'>
          <DropdownMenuItem
            disabled={!settleable}
            onClick={() => {
              setCurrentRow(row.original)
              setOpen('confirm')
            }}
          >
            {tAction('confirm')}
            <DropdownMenuShortcut>
              <BadgeCheck size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            disabled={!settleable}
            onClick={() => {
              setCurrentRow(row.original)
              setOpen('fail')
            }}
            className='text-red-500!'
          >
            {tAction('fail')}
            <DropdownMenuShortcut>
              <XCircle size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            disabled={!canIssueRefund}
            title={refundTooltip}
            onClick={() => {
              setCurrentRow(row.original)
              setOpen('refund')
            }}
          >
            {tAction('issue_refund')}
            <DropdownMenuShortcut>
              <RotateCcw size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
          <DropdownMenuItem
            disabled={!refundable}
            onClick={() => {
              setCurrentRow(row.original)
              setOpen('refunds')
            }}
          >
            {tAction('refund_history')}
            <DropdownMenuShortcut>
              <History size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  )
}
