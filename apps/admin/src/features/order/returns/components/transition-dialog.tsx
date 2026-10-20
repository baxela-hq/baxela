import { useState } from 'react'
import { AlertTriangle, PackageCheck } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'
import {
  statusTransitions,
  type OrderReturn,
  type ReturnStatus,
} from '../data/schema'
import { useUpdateReturn } from '../hooks/use-return-mutations'

type TransitionDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: OrderReturn
  status: ReturnStatus
}

/**
 * Transition confirmation for a return: an optional admin note on every
 * action, plus the restock choice on the `received` transition.
 */
export function TransitionDialog({
  open,
  onOpenChange,
  currentRow,
  status,
}: TransitionDialogProps) {
  const [adminNote, setAdminNote] = useState('')
  const [restoreStock, setRestoreStock] = useState(true)

  const { t, tLabel, tHelpText } = useAppTranslation(Locales.RETURN)

  const updateReturn = useUpdateReturn()

  const handleConfirm = () => {
    onOpenChange(false)

    updateReturn.mutate({
      id: currentRow.id.toString(),
      data: {
        status,
        admin_note: adminNote.trim() || undefined,
        ...(status === 'received' ? { restore_stock: restoreStock } : {}),
      },
    })
  }

  const allowed = (statusTransitions[currentRow.status] ?? []).includes(status)

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      handleConfirm={handleConfirm}
      disabled={!allowed}
      title={
        <span>
          <AlertTriangle
            className='me-1 inline-block stroke-destructive'
            size={18}
          />{' '}
          {t('dialog.transition.title', { status: t(`form.statuses.status.${status}`) })}
        </span>
      }
      desc={
        <div className='space-y-4'>
          <p className='mb-2'>
            {t('dialog.transition.confirmation', {
              code: currentRow.order_code ?? currentRow.order_id,
              status: t(`form.statuses.status.${status}`),
            })}
          </p>

          <Label className='flex flex-col items-start gap-1.5'>
            {tLabel('admin_note')}
            <Textarea
              rows={3}
              value={adminNote}
              onChange={(e) => setAdminNote(e.target.value)}
              placeholder={t('form.placeholders.admin_note')}
            />
          </Label>

          {status === 'received' && (
            <Label className='flex flex-row items-center gap-2'>
              <Checkbox
                checked={restoreStock}
                onCheckedChange={(checked) => setRestoreStock(!!checked)}
              />
              <span className='text-sm font-normal'>
                {tLabel('restore_stock')}
                <span className='block text-xs text-muted-foreground'>
                  {tHelpText('restore_stock')}
                </span>
              </span>
            </Label>
          )}

          {status === 'completed' && (
            <Alert>
              <PackageCheck className='size-4' />
              <AlertTitle>{t('dialog.transition.no_auto_refund_title')}</AlertTitle>
              <AlertDescription>
                {t('dialog.transition.no_auto_refund_body')}
              </AlertDescription>
            </Alert>
          )}
        </div>
      }
      confirmText={t('dialog.transition.confirm')}
      cancelBtnText={t('dialog.transition.cancel')}
    />
  )
}
