import { useCallback, useState } from 'react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Locales } from '../data/routes'
import { type MediaItem } from '../data/schema'
import { Dialogs } from './dialogs'
import { MediaBrowser } from './media-browser'
import { Provider } from './provider'

type MediaPickerDialogProps<T extends boolean = false> = {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Selection mode: single (default) or multiple. */
  multiple?: T
  /** Optional `accept` filter passed to the upload input (e.g. 'image/*'). */
  accept?: string
  /** Custom dialog title. */
  title?: string
  /**
   * Called with the selection when the user confirms.
   * Single mode yields one item, multiple mode yields the array.
   */
  onSelect: T extends true
    ? (items: MediaItem[]) => void
    : (item: MediaItem) => void
}

/**
 * Reusable media picker — a dialog wrapping the media browser in picker mode.
 *
 * @example
 * const [open, setOpen] = useState(false)
 * const [image, setImage] = useState<MediaItem | null>(null)
 * <MediaPickerDialog open={open} onOpenChange={setOpen} accept='image/*'
 *   onSelect={(item) => setImage(item)} />
 *
 * @example multiple
 * <MediaPickerDialog multiple onSelect={(items) => setGallery(items)} ... />
 */
export function MediaPickerDialog<T extends boolean = false>({
  open,
  onOpenChange,
  multiple = false as T,
  accept,
  title,
  onSelect,
}: MediaPickerDialogProps<T>) {
  const {
    tPageTitle,
    tAction: mediaTAction,
    tMessage,
  } = useAppTranslation(Locales.MEDIA)
  // Generic actions (cancel) come from common
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  const [folderId, setFolderId] = useState<number | null>(null)
  const [selectedItems, setSelectedItems] = useState<MediaItem[]>([])

  const selectedIds = new Set(selectedItems.map((item) => item.id))

  const handleToggleSelect = useCallback(
    (item: MediaItem) => {
      setSelectedItems((prev) => {
        if (multiple) {
          const exists = prev.some((selected) => selected.id === item.id)
          return exists
            ? prev.filter((selected) => selected.id !== item.id)
            : [...prev, item]
        }
        return prev.length === 1 && prev[0].id === item.id ? [] : [item]
      })
    },
    [multiple]
  )

  const handleOpenChange = (state: boolean) => {
    if (!state) {
      setSelectedItems([])
    }
    onOpenChange(state)
  }

  const handleConfirm = () => {
    if (multiple) {
      ;(onSelect as (items: MediaItem[]) => void)(selectedItems)
    } else {
      ;(onSelect as (item: MediaItem) => void)(selectedItems[0])
    }
    setSelectedItems([])
    onOpenChange(false)
  }

  return (
    <Provider>
      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent className='flex h-[85vh] w-full max-w-5xl flex-col gap-0 sm:max-w-5xl'>
          <DialogHeader className='text-start'>
            <DialogTitle>{title ?? tPageTitle('picker.title')}</DialogTitle>
            <DialogDescription>
              {tPageTitle('picker.subtitle')}
            </DialogDescription>
          </DialogHeader>

          <div className='min-h-0 flex-1 overflow-y-auto px-4 pt-2 pb-4'>
            <MediaBrowser
              folderId={folderId}
              onFolderChange={setFolderId}
              mode='picker'
              accept={accept}
              selectedIds={selectedIds}
              onSelect={handleToggleSelect}
            />
          </div>

          <DialogFooter className='items-center justify-between gap-2 border-t pt-3'>
            <p className='text-sm text-muted-foreground'>
              {tMessage('info.selected_count', {
                count: String(selectedItems.length),
              })}
            </p>
            <div className='flex items-center gap-2'>
              <Button variant='outline' onClick={() => handleOpenChange(false)}>
                {tAction('cancel')}
              </Button>
              <Button
                onClick={handleConfirm}
                disabled={selectedItems.length === 0}
              >
                {mediaTAction('select')}
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialogs currentFolderId={folderId} />
    </Provider>
  )
}
