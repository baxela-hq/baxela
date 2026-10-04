import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { useNavigate } from '@tanstack/react-router'
import { type Row } from '@tanstack/react-table'
import { Eye, Lock, Trash2, Unlock } from 'lucide-react'
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
import { FeatureRoutes, Locales } from '../data/routes'
import { type Ticket } from '../data/schema'
import { useUpdateTicketStatus } from '../hooks/use-ticket-mutations'
import { useTickets } from './provider'

type DataTableRowActionsProps = {
  row: Row<Ticket>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = useTickets()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.SUPPORT_TICKET)
  const updateStatus = useUpdateTicketStatus()
  const navigate = useNavigate()

  // quick status actions only offer transitions other than the current one;
  // "answered" is never set manually — it follows a staff reply
  const quickStatuses = (['open', 'closed'] as const).filter(
    (status) => status !== row.original.status
  )

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
            onClick={() => {
              navigate({
                to: FeatureRoutes.SHOW,
                params: { id: row.original.id.toString() },
              })
            }}
          >
            {tLabel('view')}
            <DropdownMenuShortcut>
              <Eye size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
          {quickStatuses.map((status) => (
            <DropdownMenuItem
              key={status}
              onClick={() =>
                updateStatus.mutate({ ticket: row.original, status })
              }
            >
              {tLabel(status)}
              <DropdownMenuShortcut>
                {status === 'open' && <Unlock size={16} />}
                {status === 'closed' && <Lock size={16} />}
              </DropdownMenuShortcut>
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
            <DropdownMenuShortcut>
              <Trash2 size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  )
}
