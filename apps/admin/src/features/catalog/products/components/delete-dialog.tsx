'use client'

import { useState } from 'react'
import { AlertTriangle } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type Product } from '../data/schema'
import { deleteProduct } from '../api/products.api'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { FeatureRoutes, Locales } from '../data/routes'

type DeleteDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: Product
}

export function DeleteDialog({
  open,
  onOpenChange,
  currentRow,
}: DeleteDialogProps) {
  const [value, setValue] = useState('')
  const { tMessage, tLabel: tcLabel } = useAppTranslation(Locales.SHARED_COMMON)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const queryClient = useQueryClient()

  const handleDelete = async () => {
    if (value.trim() !== currentRow.id.toString()) return

    onOpenChange(false)

    try{
      await deleteProduct(currentRow.id.toString())
      await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })

      showSubmittedData(currentRow, tMessage('success.record.deleted_general'))
    } catch (_err){
      showSubmittedData(currentRow, tMessage('error.general'))
    }
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
