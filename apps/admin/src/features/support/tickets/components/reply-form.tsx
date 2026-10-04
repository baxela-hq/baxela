import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { Locales } from '../data/routes'
import { type TicketReplyForm, replyFormSchema } from '../data/schema'
import { useReplyToTicket } from '../hooks/use-ticket-mutations'

/** The staff reply box on the ticket show page. Sending marks the ticket
 * as answered and notifies the customer (in-app + email). */
export function ReplyForm({ ticketId }: { ticketId: number }) {
  const { tAction, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.SUPPORT_TICKET)

  const form = useForm<TicketReplyForm>({
    resolver: zodResolver(replyFormSchema),
    defaultValues: { body: '' },
  })

  const replyToTicket = useReplyToTicket()

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit((data) => {
          replyToTicket.mutate(
            { id: ticketId, body: data.body },
            {
              onSuccess: () => {
                form.reset()
              },
            }
          )
        })}
        className='space-y-4'
      >
        <FormField
          control={form.control}
          name='body'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{tLabel('reply')}</FormLabel>
              <FormControl>
                <Textarea
                  rows={5}
                  {...field}
                  placeholder={tPlaceHolder('textarea')}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button type='submit' disabled={replyToTicket.isPending}>
          {tAction('submit')}
        </Button>
      </form>
    </Form>
  )
}
