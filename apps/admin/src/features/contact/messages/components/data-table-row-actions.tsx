import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import {
  Archive,
  CheckCheck,
  Eye,
  MessageSquareReply,
  Trash2,
} from 'lucide-react'
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
import { type ContactMessage } from '../data/schema'
import { useUpdateContactMessageStatus } from '../hooks/use-contact-message-mutations'
import { useContactMessages } from './provider'

type DataTableRowActionsProps = {
  row: Row<ContactMessage>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = useContactMessages()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.CONTACT_MESSAGE)
  const updateStatus = useUpdateContactMessageStatus()

  // quick status actions only offer transitions other than the current one
  const quickStatuses = (['read', 'replied', 'archived'] as const).filter(
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
              setCurrentRow(row.original)
              setOpen('view')
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
                updateStatus.mutate({ message: row.original, status })
              }
            >
              {tLabel(status)}
              <DropdownMenuShortcut>
                {status === 'read' && <CheckCheck size={16} />}
                {status === 'replied' && <MessageSquareReply size={16} />}
                {status === 'archived' && <Archive size={16} />}
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
