import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { statusBadgeVariants } from '../data/data'
import { Locales } from '../data/routes'
import { type ContactMessage } from '../data/schema'
import { useUpdateContactMessageStatus } from '../hooks/use-contact-message-mutations'

type ViewDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: ContactMessage
}

export function ViewDrawer({
  open,
  onOpenChange,
  currentRow,
}: ViewDrawerProps) {
  const { tLabel, tStatus } = useAppTranslation(Locales.CONTACT_MESSAGE)
  const { formatDateTime } = useFormatDateTime()
  const updateStatus = useUpdateContactMessageStatus()

  const badgeColor = statusBadgeVariants.get(currentRow.status)

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className='flex flex-col overflow-y-auto sm:max-w-lg'>
        <SheetHeader>
          <SheetTitle>{currentRow.subject}</SheetTitle>
          <SheetDescription className='flex flex-wrap items-center gap-2'>
            <Badge variant='outline' className={cn(badgeColor)}>
              {tStatus(`status.${currentRow.status}`)}
            </Badge>
            <span className='text-xs'>
              {formatDateTime(currentRow.created_at)}
            </span>
          </SheetDescription>
        </SheetHeader>

        <div className='flex flex-1 flex-col gap-6 px-4'>
          <div className='grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2'>
            <div>
              <p className='text-muted-foreground'>{tLabel('name')}</p>
              <p className='font-medium'>{currentRow.name}</p>
            </div>
            <div>
              <p className='text-muted-foreground'>{tLabel('email')}</p>
              <p className='font-medium break-all'>{currentRow.email}</p>
            </div>
            <div>
              <p className='text-muted-foreground'>{tLabel('phone')}</p>
              <p className='font-medium'>{currentRow.phone ?? '—'}</p>
            </div>
            <div>
              <p className='text-muted-foreground'>{tLabel('ip_address')}</p>
              <p className='font-medium'>{currentRow.ip_address ?? '—'}</p>
            </div>
          </div>

          <div>
            <p className='mb-2 text-muted-foreground'>{tLabel('content')}</p>
            <p className='rounded-md border bg-muted/40 p-4 text-sm whitespace-pre-line'>
              {currentRow.content}
            </p>
          </div>
        </div>

        <div className='flex items-center gap-2 border-t px-4 py-3'>
          <span className='text-sm text-muted-foreground'>
            {tLabel('status')}
          </span>
          <Select
            value={currentRow.status}
            onValueChange={(status) =>
              updateStatus.mutate({
                message: currentRow,
                status: status as ContactMessage['status'],
              })
            }
          >
            <SelectTrigger className='h-8 w-40'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {(['unread', 'read', 'replied', 'archived'] as const).map(
                (status) => (
                  <SelectItem key={status} value={status}>
                    {tStatus(`status.${status}`)}
                  </SelectItem>
                )
              )}
            </SelectContent>
          </Select>
        </div>
      </SheetContent>
    </Sheet>
  )
}
