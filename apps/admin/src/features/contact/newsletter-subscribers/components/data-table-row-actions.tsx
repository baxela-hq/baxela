import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { CheckCheck, Trash2, UserMinus } from 'lucide-react'
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
import { type NewsletterSubscriber } from '../data/schema'
import { useUpdateNewsletterSubscriberStatus } from '../hooks/use-newsletter-subscriber-mutations'
import { useNewsletterSubscribers } from './provider'

type DataTableRowActionsProps = {
  row: Row<NewsletterSubscriber>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = useNewsletterSubscribers()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.NEWSLETTER_SUBSCRIBER)
  const updateStatus = useUpdateNewsletterSubscriberStatus()

  // quick actions only offer the transition other than the current one
  const quickStatuses = (['subscribed', 'unsubscribed'] as const).filter(
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
          {quickStatuses.map((status) => (
            <DropdownMenuItem
              key={status}
              onClick={() =>
                updateStatus.mutate({ subscriber: row.original, status })
              }
            >
              {tLabel(status)}
              <DropdownMenuShortcut>
                {status === 'subscribed' && <CheckCheck size={16} />}
                {status === 'unsubscribed' && <UserMinus size={16} />}
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
