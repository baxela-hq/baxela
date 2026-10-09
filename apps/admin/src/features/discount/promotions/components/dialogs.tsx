import { DeleteDialog } from './delete-dialog.tsx';
import { usePromotions } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes'


export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = usePromotions()
  const queryClient = useQueryClient()

  return (
    <>
      {currentRow && (
        <DeleteDialog
          key={`promotion-delete-${currentRow.id}`}
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
