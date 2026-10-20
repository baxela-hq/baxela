import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Locales } from '../data/routes'
import { refundCreateSchema, type RefundCreate } from '../data/refund-schema'
import { useCreateRefund } from '../hooks/use-refund-mutations'
import { type Payment } from '../data/schema'

type RefundDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: Payment
}

/**
 * Issue a ledger refund against a successful payment. The amount is
 * prefilled with the payment total minus what the ledger already
 * refunded/reserved; the server validates against the same remaining
 * balance under a lock.
 */
export function RefundDialog({ open, onOpenChange, currentRow }: RefundDialogProps) {
  const { t, tLabel } = useAppTranslation(Locales.PAYMENT)

  const form = useForm<RefundCreate>({
    resolver: zodResolver(refundCreateSchema),
    defaultValues: {
      amount: String(currentRow.amount),
      reason: '',
    },
  })

  const createRefund = useCreateRefund()

  const onSubmit = (data: RefundCreate) => {
    createRefund.mutate(
      { id: currentRow.id.toString(), data },
      {
        onSuccess: () => {
          onOpenChange(false)
          form.reset()
        },
      }
    )
  }

  return (
    <Dialog open={open} onOpenChange={(v) => { onOpenChange(v); if (!v) form.reset() }}>
      <DialogContent>
        <DialogHeader className="text-start">
          <DialogTitle>{t('refund.dialog.title')}</DialogTitle>
          <DialogDescription>
            {t('refund.dialog.subtitle', { id: currentRow.id })}
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form
            id="payment-refund-form"
            onSubmit={form.handleSubmit(onSubmit)}
            className="space-y-4"
          >
            <FormField
              control={form.control}
              name="amount"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('refund_amount')}</FormLabel>
                  <FormControl>
                    <Input type="text" inputMode="decimal" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('refund_reason')}</FormLabel>
                  <FormControl>
                    <Textarea rows={3} {...field} />
                  </FormControl>
                  <FormDescription>
                    {t('refund.dialog.manual_note')}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>
        <DialogFooter className="gap-2">
          <Button form="payment-refund-form" type="submit" disabled={createRefund.isPending}>
            {t('refund.dialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
