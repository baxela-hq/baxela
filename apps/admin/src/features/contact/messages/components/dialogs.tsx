import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes'
import { DeleteDialog } from './delete-dialog'
import { useContactMessages } from './provider'
import { ViewDrawer } from './view-drawer'

export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useContactMessages()
  const queryClient = useQueryClient()

  return (
    <>
      {currentRow && (
        <>
          <ViewDrawer
            key={`contact-message-view-${currentRow.id}`}
            open={open === 'view'}
            onOpenChange={() => {
              setOpen('view')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
          />

          <DeleteDialog
            key={`contact-message-delete-${currentRow.id}`}
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
