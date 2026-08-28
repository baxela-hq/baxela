import { Folder } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from '@/components/ui/context-menu'
import { Locales } from '../data/routes'
import { type MediaFolder } from '../data/schema'
import { useMediaActions } from '../hooks/use-media-actions'

type FolderCardProps = {
  folder: MediaFolder
  /** 'picker' hides destructive/edit actions (read-only navigation). */
  mode: 'manage' | 'picker'
  onOpen: (folder: MediaFolder) => void
}

export function FolderCard({ folder, mode, onOpen }: FolderCardProps) {
  const { tAction: mediaTAction } = useAppTranslation(Locales.MEDIA)
  // Generic actions (delete) come from common
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { openFolderEdit, openFolderDelete } = useMediaActions()

  return (
    <ContextMenu>
      <ContextMenuTrigger asChild>
        <button
          type='button'
          onClick={() => onOpen(folder)}
          onDoubleClick={() => onOpen(folder)}
          className='flex w-full flex-col items-center gap-2 rounded-lg border bg-card p-4 text-center transition-colors outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring'
        >
          <Folder className='size-10 text-muted-foreground' strokeWidth={1.5} />
          <span
            className='w-full truncate text-sm font-medium'
            title={folder.name}
          >
            {folder.name}
          </span>
        </button>
      </ContextMenuTrigger>
      <ContextMenuContent>
        <ContextMenuItem onClick={() => onOpen(folder)}>
          {mediaTAction('open')}
        </ContextMenuItem>
        {mode === 'manage' && (
          <>
            <ContextMenuSeparator />
            <ContextMenuItem onClick={() => openFolderEdit(folder)}>
              {mediaTAction('rename_move')}
            </ContextMenuItem>
            <ContextMenuItem
              variant='destructive'
              onClick={() => openFolderDelete(folder)}
            >
              {tAction('delete')}
            </ContextMenuItem>
          </>
        )}
      </ContextMenuContent>
    </ContextMenu>
  )
}
