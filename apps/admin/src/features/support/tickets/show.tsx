import { getRouteApi, useNavigate } from '@tanstack/react-router'
import { ArrowLeftIcon, ListCheckIcon } from 'lucide-react'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget as SkeletonWidgetFromFile } from '@/components/shared/skeleton-widget'
import { HeaderActions } from '@/components/layout/header-actions'
import { statusBadgeVariants } from './data/data'
import { FeatureRoutes, Locales } from './data/routes'
import { type TicketStatus } from './data/schema'
import { useOneTicket } from './hooks/use-tickets'
import { useUpdateTicketStatus } from './hooks/use-ticket-mutations'
import { ReplyForm } from './components/reply-form'

const route = getRouteApi('/_authenticated/support/tickets/$id/show')

export function TicketShow() {
  const { id } = route.useParams()
  const navigate = useNavigate()
  const { tPageTitle, tLabel: tcLabel } = useAppTranslation(
    Locales.SHARED_COMMON
  )
  const { tLabel, tStatus } = useAppTranslation(Locales.SUPPORT_TICKET)
  const { formatDateTime } = useFormatDateTime()
  const entityName = {
    singular: tLabel('ticket'),
    plural: tLabel('tickets'),
  }

  const { data: ticket, isLoading } = useOneTicket(id)
  const updateStatus = useUpdateTicketStatus()

  return (
    <>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle('show.title', {
                entity: entityName.singular,
                id: id ? id.toString() : '',
              })}
            </h2>
            <p className='text-muted-foreground'>{tPageTitle('show.subtitle')}</p>
          </div>
          <div className='flex gap-2'>
            <Button
              variant='outline'
              className='space-x-1'
              onClick={() => navigate({ to: FeatureRoutes.LIST })}
            >
              <ArrowLeftIcon size={16} />
              <span>{entityName.plural}</span>
              <ListCheckIcon size={18} />
            </Button>
          </div>
        </div>

        <div className='w-full space-y-3'>
          {isLoading && <SkeletonWidgetFromFile />}

          {ticket && (
            <>
              {/* TICKET INFORMATION */}
              <Card className='w-full'>
                <CardHeader>
                  <CardTitle>{ticket.subject}</CardTitle>
                </CardHeader>

                <CardContent className='space-y-4'>
                  <div className='flex flex-wrap items-center gap-3'>
                    <Badge
                      variant='outline'
                      className={cn(statusBadgeVariants.get(ticket.status))}
                    >
                      {tStatus(`status.${ticket.status}`)}
                    </Badge>
                    <Select
                      value={ticket.status}
                      onValueChange={(status) =>
                        updateStatus.mutate({
                          ticket,
                          status: status as TicketStatus,
                        })
                      }
                    >
                      <SelectTrigger className='h-8 w-40'>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {(['open', 'answered', 'closed'] as const).map(
                          (status) => (
                            <SelectItem key={status} value={status}>
                              {tStatus(`status.${status}`)}
                            </SelectItem>
                          )
                        )}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className='grid gap-x-4 gap-y-2 text-sm sm:grid-cols-3'>
                    <div>
                      <p className='text-muted-foreground'>
                        {tLabel('customer')}
                      </p>
                      <p className='font-medium'>
                        {ticket.customer_name ?? `#${ticket.user_id}`}
                      </p>
                    </div>
                    <div>
                      <p className='text-muted-foreground'>
                        {tLabel('customer_email')}
                      </p>
                      <p className='font-medium break-all'>
                        {ticket.customer_email ?? '—'}
                      </p>
                    </div>
                    <div>
                      <p className='text-muted-foreground'>
                        {tLabel('order_code')}
                      </p>
                      <p className='font-mono font-medium'>
                        {ticket.order_code ?? '—'}
                      </p>
                    </div>
                    <div>
                      <p className='text-muted-foreground'>
                        {tLabel('last_message_at')}
                      </p>
                      <p className='font-medium'>
                        {ticket.last_message_at
                          ? formatDateTime(ticket.last_message_at)
                          : '—'}
                      </p>
                    </div>
                    <div>
                      <p className='text-muted-foreground'>
                        {tLabel('created_at')}
                      </p>
                      <p className='font-medium'>
                        {formatDateTime(ticket.created_at)}
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* CONVERSATION */}
              <Card>
                <CardHeader>
                  <CardTitle>
                    {tcLabel('items')} ({ticket.messages?.length ?? 0})
                  </CardTitle>
                </CardHeader>

                <CardContent className='space-y-6'>
                  <ul className='space-y-4'>
                    {(ticket.messages ?? []).map((message) => {
                      const staff = message.sender === 'admin'
                      return (
                        <li
                          key={message.id}
                          className={cn(
                            'max-w-[85%] rounded-md border p-4 text-sm',
                            staff
                              ? 'border-muted bg-muted/40'
                              : 'bg-background'
                          )}
                        >
                          <p className='mb-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground'>
                            <Badge variant='outline'>
                              {tLabel(`sender.${message.sender}`)}
                            </Badge>
                            {staff ? (
                              <span className='font-medium text-foreground'>
                                {message.author_name ??
                                  message.author_email ??
                                  `#${message.user_id}`}
                              </span>
                            ) : null}
                            <span>
                              {formatDateTime(message.created_at)}
                            </span>
                          </p>
                          <p className='whitespace-pre-line'>
                            {message.body}
                          </p>
                        </li>
                      )
                    })}
                  </ul>

                  <ReplyForm ticketId={ticket.id} />
                </CardContent>
              </Card>
            </>
          )}
        </div>
      </Main>
    </>
  )
}
