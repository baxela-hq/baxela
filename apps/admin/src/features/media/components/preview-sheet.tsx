import { Download, Link2, Pencil, Trash2 } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Locales } from '../data/routes'
import {
  formatBytes,
  getDisplayName,
  getMediaUrl,
  isImage,
  isVideo,
  type MediaItem,
} from '../data/schema'
import { formatDate } from '@/shared/lib/datetime.ts'
import { useMediaActions } from '../hooks/use-media-actions'
import { MediaThumb } from './media-card'

type PreviewSheetProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: MediaItem
}

export function PreviewSheet({
  open,
  onOpenChange,
  currentRow,
}: PreviewSheetProps) {
  const { tLabel, tAction: mediaTAction } = useAppTranslation(Locales.MEDIA)
  // Generic actions (download/delete) come from common
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { copyUrl, download, openMediaEdit, openMediaDelete } =
    useMediaActions()

  const url = getMediaUrl(currentRow)
  const isPreviewable = (isImage(currentRow) || isVideo(currentRow)) && !!url

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className='flex w-full flex-col gap-0 sm:max-w-md'>
        <SheetHeader className='text-start'>
          <SheetTitle className='truncate pe-6'>
            {getDisplayName(currentRow)}
          </SheetTitle>
          <SheetDescription>{formatBytes(currentRow.size)}</SheetDescription>
        </SheetHeader>

        <div className='flex-1 space-y-4 overflow-y-auto px-4 pb-4'>
          {isPreviewable ? (
            isVideo(currentRow) ? (
              <video
                src={url ?? undefined}
                controls
                className='w-full rounded-lg bg-muted'
              />
            ) : (
              <img
                src={url ?? undefined}
                alt={getDisplayName(currentRow)}
                className='w-full rounded-lg bg-muted object-contain'
              />
            )
          ) : (
            <div className='flex aspect-square items-center justify-center rounded-lg bg-muted'>
              <MediaThumb item={currentRow} />
            </div>
          )}

          <Separator />

          <dl className='space-y-2 text-sm'>
            <div className='flex items-center justify-between gap-4'>
              <dt className='shrink-0 text-muted-foreground'>
                {tLabel('filename')}
              </dt>
              <dd
                className='min-w-0 flex-1 truncate text-end font-medium'
                title={currentRow.filename}
              >
                {currentRow.filename}
              </dd>
            </div>
            <div className='flex items-center justify-between gap-4'>
              <dt className='shrink-0 text-muted-foreground'>
                {tLabel('type')}
              </dt>
              <dd className='truncate font-medium'>
                {currentRow.mime_type ?? '—'}
              </dd>
            </div>
            <div className='flex items-center justify-between gap-4'>
              <dt className='shrink-0 text-muted-foreground'>
                {tLabel('size')}
              </dt>
              <dd className='truncate font-medium'>
                {formatBytes(currentRow.size)}
              </dd>
            </div>
            <div className='flex items-center justify-between gap-4'>
              <dt className='shrink-0 text-muted-foreground'>
                {tLabel('created_at')}
              </dt>
              <dd className='truncate font-medium'>
                {formatDate(currentRow.created_at)}
              </dd>
            </div>
            {url && (
              <div className='flex items-center justify-between gap-4'>
                <dt className='shrink-0 text-muted-foreground'>
                  {tLabel('url')}
                </dt>
                <dd
                  className='min-w-0 flex-1 truncate text-end font-medium'
                  title={url}
                >
                  {url}
                </dd>
              </div>
            )}
          </dl>

          <div className='grid grid-cols-2 gap-2 pt-2'>
            <Button
              variant='outline'
              size='sm'
              onClick={() => copyUrl(currentRow)}
            >
              <Link2 /> {mediaTAction('copy_url')}
            </Button>
            <Button
              variant='outline'
              size='sm'
              onClick={() => download(currentRow)}
            >
              <Download /> {tAction('download')}
            </Button>
            <Button
              variant='outline'
              size='sm'
              onClick={() => openMediaEdit(currentRow)}
            >
              <Pencil /> {mediaTAction('rename_move')}
            </Button>
            <Button
              variant='outline'
              size='sm'
              className='text-destructive'
              onClick={() => openMediaDelete(currentRow)}
            >
              <Trash2 /> {tAction('delete')}
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  )
}
