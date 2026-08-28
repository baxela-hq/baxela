import { DeleteDialog } from './delete-dialog.tsx';
import { useOptionValues } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes.ts'
import { MutateDrawer } from './mutate-drawer.tsx'

type DialogProps= {
  optionId: string
}
export function Dialogs({optionId}: DialogProps) {
  const { open, setOpen, currentRow, setCurrentRow } = useOptionValues()
  const queryClient = useQueryClient()

  return (
    <>
      <MutateDrawer
        key='category-create'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
        optionId={optionId}
      />

      {currentRow && (
        <>

          <MutateDrawer
            key={`category-update-${currentRow.id}`}
            open={open === 'edit'}
            onOpenChange={() => {
              setOpen('edit')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
            optionId={optionId}
          />


          <DeleteDialog
            key={`category-delete-${currentRow.id}`}
            open={open === 'delete'}
            onOpenChange={() => {
              setOpen('delete')
              setTimeout(async () => {
                setCurrentRow(null)
                await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
              }, 500)
            }}
            currentRow={currentRow}
            optionId={optionId}
          />
        </>
      )}
    </>
  )
}
