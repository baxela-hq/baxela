import { useEffect, useState } from 'react'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { AlertTriangle, Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { deleteFolder, deleteMedia } from '../api/media.api'
import { Locales } from '../data/routes'
import {
  getDisplayName,
  type MediaFolder,
  type MediaItem,
} from '../data/schema'
import { useInvalidateMedia } from '../hooks/use-media-actions'

type DeleteDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: MediaFolder | MediaItem
}

// `filename` only exists on media items — folders don't have it
// (both entities have `name`, so it can't discriminate)
function isMediaFolder(row: MediaFolder | MediaItem): row is MediaFolder {
  return (row as MediaItem).filename === undefined
}

export function DeleteDialog({
  open,
  onOpenChange,
  currentRow,
}: DeleteDialogProps) {
  const [value, setValue] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const invalidateMedia = useInvalidateMedia()
  // Delete-dialog texts come from the shared data-table namespace (same as
  // the other features' delete dialogs); messages/labels from common; media
  // only for entity labels and the folder-specific warning body
  const { tMessage, tLabel: commonTLabel } = useAppTranslation(
    Locales.SHARED_COMMON
  )
  const { t: tDataTable } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const { tMessage: mediaTMessage, tLabel } = useAppTranslation(Locales.MEDIA)

  const isFolder = isMediaFolder(currentRow)
  // Admins confirm by typing the entity's name — IDs aren't visible anywhere
  // in the media UI, so the name is the natural identifier here
  const confirmWord = isFolder ? currentRow.name : getDisplayName(currentRow)

  // Clear the confirmation input when switching between items
  useEffect(() => {
    setValue('')
  }, [currentRow])

  const handleDelete = async () => {
    if (value.trim() !== confirmWord) return

    setIsLoading(true)
    try {
      if (isFolder) {
        await deleteFolder(currentRow.id)
      } else {
        await deleteMedia(currentRow.id)
      }
      toast.success(tMessage('success.record.deleted', { name: confirmWord }))
      await invalidateMedia()
      onOpenChange(false)
    } catch (err: unknown) {
      const isMissing = err instanceof ApiError && err.status === 404
      if (isMissing) {
        // e.g. seeder rows whose physical file is gone — nothing to retry,
        // so show a generic message and close the dialog
        toast.error(tMessage('error.record.not_found'))
        onOpenChange(false)
      } else if (err instanceof ApiError) {
        parseAndToastError(err)
      } else {
        toast.error(tMessage('error.general'))
      }
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      handleConfirm={handleDelete}
      disabled={value.trim() !== confirmWord}
      isLoading={isLoading}
      destructive
      title={
        <span className='text-destructive'>
          <AlertTriangle
            className='me-1 inline-block stroke-destructive'
            size={18}
          />{' '}
          {tDataTable('dialog.delete.delete_item')}
        </span>
      }
      desc={
        <div className='space-y-4'>
          <p className='mb-2'>
            {tDataTable('dialog.delete.delete_confirmation')}{' '}
            <span className='font-bold'>
              {confirmWord} ({commonTLabel('id')}: {currentRow.id})
            </span>
            ?
          </p>

          <Label className='my-2'>
            {tLabel('name')}:
            <Input
              value={value}
              onChange={(e) => setValue(e.target.value)}
              placeholder={tDataTable('dialog.delete.enter_to_confirm', {
                word: confirmWord,
              })}
              className='mt-1'
            />
          </Label>

          <Alert variant='destructive'>
            <AlertTitle>{tDataTable('dialog.delete.warning')}!</AlertTitle>
            <AlertDescription>
              {isFolder
                ? mediaTMessage('dialog.delete.folder_warning_body')
                : tDataTable('dialog.delete.operation_warning')}
            </AlertDescription>
          </Alert>
        </div>
      }
      confirmText={
        <>
          {isLoading && <Loader2 className='animate-spin' />}
          {tDataTable('dialog.delete.delete')}
        </>
      }
      cancelBtnText={tDataTable('dialog.delete.cancel')}
    />
  )
}
