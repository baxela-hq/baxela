import { DeleteDialog } from './delete-dialog.tsx';
import { useAttributeTemplates } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes.ts'
import { MutateDrawer } from './mutate-drawer.tsx'


export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useAttributeTemplates()
  const queryClient = useQueryClient()

  return (
    <>
      <MutateDrawer
        key='attribute-template-create'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
      />

      {currentRow && (
        <>

          <MutateDrawer
            key={`attribute-template-update-${currentRow.id}`}
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
            key={`attribute-template-delete-${currentRow.id}`}
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
