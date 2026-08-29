'use client'

import { useState } from 'react'
import { type Table } from '@tanstack/react-table'
import { AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Locales } from '../data/routes';
import { useBulkDeleteAttributeGroups } from '../hooks/use-attribute-group-mutations'
import { type AttributeGroup } from '../data/schema'

type MultiDeleteDialogProps<TData> = {
  open: boolean
  onOpenChange: (open: boolean) => void
  table: Table<TData>
}

const CONFIRM_WORD = 'DELETE'

export function MultiDeleteDialog<TData>({
  open,
  onOpenChange,
  table,
}: MultiDeleteDialogProps<TData>) {
  const [value, setValue] = useState('')

  const selectedRows = table.getFilteredSelectedRowModel().rows

  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const bulkDeleteAttributeGroups = useBulkDeleteAttributeGroups()

  const handleDelete = () => {
    if (value.trim() !== CONFIRM_WORD) {
      toast.error(t('dialog.bulk_delete.type_to_confirm', {word: CONFIRM_WORD}))
      return
    }

    onOpenChange(false)

    toast.promise(
      bulkDeleteAttributeGroups.mutateAsync(
        selectedRows.map((row) => row.original as AttributeGroup)
      ),
      {
      loading: t('dialog.bulk_delete.deleting-items'),
      success: () => {
        setValue('')
        table.resetRowSelection()
        return t('dialog.bulk_delete.deleted_items_x', {
          n: selectedRows.length,
          name: selectedRows.length > 1 ? t('shared.items') : t('shared.item')
        })
      },
      error: tMessage('error.general'),
    })
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      handleConfirm={handleDelete}
      disabled={value.trim() !== CONFIRM_WORD}
      title={
        <span className='text-destructive'>
          <AlertTriangle
            className='me-1 inline-block stroke-destructive'
            size={18}
          />{' '}
          {
            t('dialog.bulk_delete.delete_items_x', {
              n: selectedRows.length,
              name: selectedRows.length > 1 ? t('shared.items') : t('shared.item')
            })
          }
        </span>
      }
      desc={
        <div className='space-y-4'>
          <p className='mb-2'>
            {t('dialog.bulk_delete.delete_confirmation')} <br />
            {t('dialog.bulk_delete.action_warning')}
          </p>

          <Label className='my-4 flex flex-col items-start gap-1.5'>
            <span className=''>{t('dialog.bulk_delete.confirm_by_typing')} "{CONFIRM_WORD}":</span>
            <Input
              value={value}
              onChange={(e) => setValue(e.target.value)}
              placeholder={t('dialog.bulk_delete.type_to_confirm', {word: CONFIRM_WORD})}
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
