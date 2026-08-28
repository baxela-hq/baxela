import { DeleteDialog } from './delete-dialog'
import { FolderMutateDialog } from './folder-mutate-dialog'
import { MediaMutateDialog } from './media-mutate-dialog'
import { PreviewSheet } from './preview-sheet'
import { useMediaDialogs } from './provider'

type DialogsProps = {
  /** Currently browsed folder — used as default parent for new folders. */
  currentFolderId: number | null
}

export function Dialogs({ currentFolderId }: DialogsProps) {
  const { open, setOpen, currentTarget, setCurrentTarget } = useMediaDialogs()

  const closeAfter = (type: Parameters<typeof setOpen>[0]) => {
    setOpen(type)
    setTimeout(() => setCurrentTarget(null), 500)
  }

  return (
    <>
      <FolderMutateDialog
        key='folder-create'
        open={open === 'folder-add'}
        onOpenChange={() => closeAfter('folder-add')}
        defaultParentId={currentFolderId}
      />

      {currentTarget?.type === 'folder' && (
        <>
          <FolderMutateDialog
            key={`folder-update-${currentTarget.folder.id}`}
            open={open === 'folder-edit'}
            onOpenChange={() => closeAfter('folder-edit')}
            currentRow={currentTarget.folder}
          />

          <DeleteDialog
            key={`folder-delete-${currentTarget.folder.id}`}
            open={open === 'delete'}
            onOpenChange={() => closeAfter('delete')}
            currentRow={currentTarget.folder}
          />
        </>
      )}

      {currentTarget?.type === 'media' && (
        <>
          <MediaMutateDialog
            key={`media-update-${currentTarget.item.id}`}
            open={open === 'media-edit'}
            onOpenChange={() => closeAfter('media-edit')}
            currentRow={currentTarget.item}
          />

          <DeleteDialog
            key={`media-delete-${currentTarget.item.id}`}
            open={open === 'delete'}
            onOpenChange={() => closeAfter('delete')}
            currentRow={currentTarget.item}
          />

          <PreviewSheet
            key={`media-preview-${currentTarget.item.id}`}
            open={open === 'preview'}
            onOpenChange={() => closeAfter('preview')}
            currentRow={currentTarget.item}
          />
        </>
      )}
    </>
  )
}
