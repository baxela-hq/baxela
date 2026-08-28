import { CheckCircle2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from '@/components/ui/context-menu'
import { Locales } from '../data/routes'
import {
  formatBytes,
  getDisplayName,
  getFileIcon,
  getMediaUrl,
  isImage,
  isVideo,
  type MediaItem,
} from '../data/schema'
import { useMediaActions } from '../hooks/use-media-actions'

type MediaCardProps = {
  item: MediaItem
  mode: 'manage' | 'picker'
  selected?: boolean
  onSelect?: (item: MediaItem) => void
}

export function MediaThumb({
  item,
  className,
}: {
  item: MediaItem
  className?: string
}) {
  const url = getMediaUrl(item)

  if (isImage(item) && url) {
    return (
      <img
        src={url}
        alt={getDisplayName(item)}
        loading='lazy'
        className={cn('size-full object-cover', className)}
      />
    )
  }
  if (isVideo(item) && url) {
    return (
      <video
        src={url}
        className={cn('size-full object-cover', className)}
        muted
        playsInline
        preload='metadata'
      />
    )
  }
  const Icon = getFileIcon(item)
  return (
    <div
      className={cn('flex size-full items-center justify-center', className)}
    >
      {/* eslint-disable-next-line react-hooks/static-components -- lucide icons are stateless */}
      <Icon className='size-10 text-muted-foreground' strokeWidth={1.5} />
    </div>
  )
}

export function MediaCard({
  item,
  mode,
  selected = false,
  onSelect,
}: MediaCardProps) {
  const { tAction: mediaTAction } = useAppTranslation(Locales.MEDIA)
  // Generic actions (download/delete) come from common
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const {
    openMediaPreview,
    openMediaEdit,
    openMediaDelete,
    copyUrl,
    download,
  } = useMediaActions()

  const handleClick = () => {
    if (mode === 'picker') {
      onSelect?.(item)
    } else {
      openMediaPreview(item)
    }
  }

  return (
    <ContextMenu>
      <ContextMenuTrigger asChild>
        <button
          type='button'
          onClick={handleClick}
          className={cn(
            'group relative flex w-full flex-col overflow-hidden rounded-lg border bg-card text-start transition-colors outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring',
            selected && 'border-primary ring-2 ring-primary'
          )}
        >
          <div className='relative aspect-square w-full bg-muted'>
            <MediaThumb item={item} />
            {mode === 'picker' && (
              <span
                className={cn(
                  'absolute end-1.5 top-1.5 flex size-5 items-center justify-center rounded-full border bg-background/80 opacity-0 transition-opacity group-hover:opacity-100',
                  selected && 'opacity-100'
                )}
              >
                {selected && (
                  <CheckCircle2 className='size-4 fill-primary text-background' />
                )}
              </span>
            )}
          </div>
          <div className='w-full space-y-0.5 p-2'>
            <p
              className='w-full truncate text-xs font-medium'
              title={getDisplayName(item)}
            >
              {getDisplayName(item)}
            </p>
            <p className='text-[11px] text-muted-foreground'>
              {formatBytes(item.size)}
            </p>
          </div>
        </button>
      </ContextMenuTrigger>
      <ContextMenuContent>
        {mode === 'picker' && (
          <ContextMenuItem onClick={() => onSelect?.(item)}>
            {mediaTAction(selected ? 'deselect' : 'select')}
          </ContextMenuItem>
        )}
        <ContextMenuItem onClick={() => openMediaPreview(item)}>
          {mediaTAction('preview')}
        </ContextMenuItem>
        <ContextMenuSeparator />
        <ContextMenuItem onClick={() => copyUrl(item)}>
          {mediaTAction('copy_url')}
        </ContextMenuItem>
        <ContextMenuItem onClick={() => download(item)}>
          {tAction('download')}
        </ContextMenuItem>
        {mode === 'manage' && (
          <>
            <ContextMenuSeparator />
            <ContextMenuItem onClick={() => openMediaEdit(item)}>
              {mediaTAction('rename_move')}
            </ContextMenuItem>
            <ContextMenuItem
              variant='destructive'
              onClick={() => openMediaDelete(item)}
            >
              {tAction('delete')}
            </ContextMenuItem>
          </>
        )}
      </ContextMenuContent>
    </ContextMenu>
  )
}
