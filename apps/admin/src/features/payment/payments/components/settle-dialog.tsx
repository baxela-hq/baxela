import { AlertTriangle } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { Locales } from '../data/routes'
import { type Payment, type PaymentUpdate } from '../data/schema'
import { useSettlePayment } from '../hooks/use-payment-mutations'

type SettleDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: Payment
  status: Extract<PaymentUpdate['status'], 'success' | 'failed'>
}

export function SettleDialog({
  open,
  onOpenChange,
  currentRow,
  status,
}: SettleDialogProps) {
  const settlePayment = useSettlePayment()
  const { t } = useAppTranslation(Locales.PAYMENT)
  const { tHelpText } = useAppTranslation(Locales.PAYMENT)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const failed = status === 'failed'

  const handleConfirm = () => {
    onOpenChange(false)

    settlePayment.mutate({ id: currentRow.id.toString(), data: { status } })
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      handleConfirm={handleConfirm}
      title={
        failed ? (
          <span className='text-destructive'>
            <AlertTriangle
              className='me-1 inline-block stroke-destructive'
              size={18}
            />{' '}
            {t(`dialog.${status}.title`)}
          </span>
        ) : (
          t(`dialog.${status}.title`)
        )
      }
      desc={
        <div className='space-y-2'>
          <p>{t(`dialog.${status}.desc`, { id: currentRow.id })}</p>
          <p className='text-muted-foreground'>
            {tHelpText(failed ? 'fail' : 'confirm')}
          </p>
        </div>
      }
      confirmText={t(`dialog.${status}.confirm`)}
      cancelBtnText={tAction('cancel')}
      destructive={failed}
    />
  )
}
