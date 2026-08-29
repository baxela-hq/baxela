'use client'

import { useState } from 'react'
import { AlertTriangle } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type Page } from '../data/schema'
import { useDeletePage } from '../hooks/use-page-mutations'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'

type PageDeleteDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: Page
}

export function DeleteDialog({
  open,
  onOpenChange,
  currentRow,
}: PageDeleteDialogProps) {
  const [value, setValue] = useState('')
  const { tLabel: tcLabel } = useAppTranslation(Locales.SHARED_COMMON)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)

  const deletePage = useDeletePage()

  const handleDelete = () => {
    if (value.trim() !== currentRow.id.toString()) return

    onOpenChange(false)

    deletePage.mutate(currentRow)
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      handleConfirm={handleDelete}
      disabled={value.trim() !== currentRow.id.toString()}
      title={
        <span className='text-destructive'>
          <AlertTriangle
            className='me-1 inline-block stroke-destructive'
            size={18}
          />{' '}
          {t('dialog.delete.delete_item')}
        </span>
      }
      desc={
        <div className='space-y-4'>
          <p className='mb-2'>
            {t('dialog.delete.delete_confirmation')} { ' ' }
            <span className='font-bold'>{tcLabel('id')}:{currentRow.id}</span>?
          </p>

          <Label className='my-2'>
            {tcLabel('id')}:
            <Input
              value={value}
              onChange={(e) => setValue(e.target.value)}
              placeholder={t('dialog.delete.enter_to_confirm', {word: currentRow.id.toString()})}
            />
          </Label>

          <Alert variant='destructive'>
            <AlertTitle>{t('dialog.delete.warning')}!</AlertTitle>
            <AlertDescription>
              {t('dialog.delete.operation_warning')}
            </AlertDescription>
          </Alert>
        </div>
      }
      confirmText={t('dialog.delete.delete')}
      cancelBtnText={t('dialog.delete.cancel')}
      destructive
    />
  )
}
