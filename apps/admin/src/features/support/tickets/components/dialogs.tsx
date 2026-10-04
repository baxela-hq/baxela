import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes'
import { DeleteDialog } from './delete-dialog'
import { useTickets } from './provider'

export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useTickets()
  const queryClient = useQueryClient()

  return (
    <>
      {currentRow && (
        <DeleteDialog
          key={`ticket-delete-${currentRow.id}`}
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
      )}
    </>
  )
}
