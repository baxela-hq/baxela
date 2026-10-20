import { DeleteDialog } from './delete-dialog.tsx';
import { useReturns } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes'
import { TransitionDialog } from './transition-dialog'
import { type ReturnStatus } from '../data/schema'


export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useReturns()
  const queryClient = useQueryClient()

  const transitions: ReturnStatus[] = [
    'approved',
    'rejected',
    'shipped',
    'received',
    'completed',
    'cancelled',
  ]

  return (
    <>
      {currentRow &&
        transitions.map((status) => (
          <TransitionDialog
            key={`return-${status}-${currentRow.id}`}
            open={open === status}
            onOpenChange={() => setOpen(status)}
            currentRow={currentRow}
            status={status}
          />
        ))}

      {currentRow && (
        <DeleteDialog
          key={`return-delete-${currentRow.id}`}
          open={open === 'delete'}
          onOpenChange={() => {
            setOpen('delete')
            setTimeout(async () => {
              setCurrentRow(null)
              await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
            }, 500)
          }}
          currentRow={currentRow}
        />
      )}
    </>
  )
}
