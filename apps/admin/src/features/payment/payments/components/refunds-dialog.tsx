import { useState } from 'react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Locales } from '../data/routes'
import { useRefundsList, useResolveRefund } from '../hooks/use-refund-mutations'
import { type Payment } from '../data/schema'
import { cn } from '@/lib/utils'

const refundStatusVariants = new Map<string, string>([
  ['pending', 'bg-amber-100/30 text-amber-900 dark:text-amber-200 border-amber-200'],
  [
    'processing',
    'bg-sky-100/30 text-sky-900 dark:text-sky-200 border-sky-200',
  ],
  ['success', 'bg-emerald-100/30 text-emerald-900 dark:text-emerald-200 border-emerald-200'],
  ['failed', 'bg-neutral-300/40 border-neutral-300'],
  ['reversed', 'bg-violet-100/30 text-violet-900 dark:text-violet-200 border-violet-200'],
  [
    'reconciliation_required',
    'bg-rose-100/30 text-rose-900 dark:text-rose-200 border-rose-200',
  ],
])

type RefundsDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: Payment
}

/**
 * Refund history for a payment. Rows awaiting a definitive outcome —
 * ambiguous ones (reconciliation_required, e.g. a gateway timeout) and
 * async-accepted ones (processing, until the gateway's webhook
 * confirms) — keep their amount reserved until an operator verifies the
 * provider's actual outcome and resolves them here. Reservations are
 * never released by age.
 */
export function RefundsDialog({ open, onOpenChange, currentRow }: RefundsDialogProps) {
  const { t, tStatus } = useAppTranslation(Locales.PAYMENT)
  const { data, isLoading } = useRefundsList(
    open ? currentRow.id.toString() : ''
  )
  const resolveRefund = useResolveRefund()
  const [resolving, setResolving] = useState<number | null>(null)
  const [transactionId, setTransactionId] = useState('')

  const refunds = data?.data ?? []
  const unresolved = refunds.filter(
    (refund) =>
      refund.status === 'reconciliation_required' ||
      refund.status === 'processing'
  )

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader className="text-start">
          <DialogTitle>{t('refund.history.title')}</DialogTitle>
          <DialogDescription>
            {t('refund.history.subtitle', { id: currentRow.id })}
          </DialogDescription>
        </DialogHeader>

        {isLoading && (
          <p className="py-4 text-center text-sm text-muted-foreground">...</p>
        )}

        {!isLoading && refunds.length === 0 && (
          <p className="py-4 text-center text-sm text-muted-foreground">
            {t('refund.history.empty')}
          </p>
        )}

        <ul className="max-h-80 space-y-2 overflow-y-auto">
          {refunds.map((refund) => (
            <li
              key={refund.id}
              className="rounded-md border p-3 text-sm space-y-2"
            >
              <div className="flex items-center justify-between gap-2">
                <span className="font-semibold tabular-nums">
                  {Number(refund.amount).toLocaleString()}
                </span>
                <Badge
                  variant="outline"
                  className={cn(refundStatusVariants.get(refund.status))}
                >
                  {tStatus(`refund_status.${refund.status}`)}
                </Badge>
              </div>
              <p className="text-muted-foreground">{refund.reason}</p>
              {refund.transaction_id && (
                <p className="font-mono text-xs text-muted-foreground">
                  {refund.transaction_id}
                </p>
              )}

              {(refund.status === 'reconciliation_required' ||
                refund.status === 'processing') &&
                (resolving === refund.id ? (
                  <div className="space-y-2 pt-1">
                    <Input
                      value={transactionId}
                      onChange={(e) => setTransactionId(e.target.value)}
                      placeholder={t('refund.history.gateway_id')}
                    />
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        disabled={!transactionId.trim()}
                        onClick={() => {
                          resolveRefund.mutate({
                            id: refund.id.toString(),
                            data: {
                              status: 'success',
                              transaction_id: transactionId.trim(),
                            },
                          })
                          setResolving(null)
                          setTransactionId('')
                        }}
                      >
                        {t('refund.history.confirm_success')}
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          resolveRefund.mutate({
                            id: refund.id.toString(),
                            data: { status: 'failed' },
                          })
                          setResolving(null)
                          setTransactionId('')
                        }}
                      >
                        {t('refund.history.confirm_failed')}
                      </Button>
                    </div>
                  </div>
                ) : (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setResolving(refund.id)}
                  >
                    {t('refund.history.resolve')}
                  </Button>
                ))}
            </li>
          ))}
        </ul>

        {unresolved.length > 0 && (
          <p className="text-xs text-muted-foreground">
            {t('refund.history.reserved_note')}
          </p>
        )}
      </DialogContent>
    </Dialog>
  )
}
