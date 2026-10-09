import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes'
import { DeleteDialog } from './delete-dialog'
import { MutateDrawer } from './mutate-drawer'
import { ReplyDrawer } from './reply-drawer'
import { usePostComments } from './provider'

export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = usePostComments()
  const queryClient = useQueryClient()

  return (
    <>
      {currentRow && (
        <>
          <ReplyDrawer
            key={`post-comment-reply-${currentRow.id}`}
            open={open === 'add'}
            onOpenChange={() => {
              setOpen('add')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
          />

          <MutateDrawer
            key={`post-comment-update-${currentRow.id}`}
            open={open === 'edit'}
            onOpenChange={() => {
              setOpen('edit')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
          />

          <DeleteDialog
            key={`post-comment-delete-${currentRow.id}`}
            open={open === 'delete'}
            onOpenChange={() => {
              setOpen('delete')
              setTimeout(async () => {
                setCurrentRow(null)
                await queryClient.invalidateQueries({
                  queryKey: [FeatureRoutes.CACHE_KEY],
                })
              }, 500)
            }}
            currentRow={currentRow}
          />
        </>
      )}
    </>
  )
}
