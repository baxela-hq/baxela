import { DeleteDialog } from './delete-dialog.tsx';
import { usePages } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes.ts'


export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = usePages()
  const queryClient = useQueryClient()

  return (
    <>
      {currentRow && (
        <>
          <DeleteDialog
            key={`page-delete-${currentRow.id}`}
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
        </>
      )}
    </>
  )
}
