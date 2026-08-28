import { DeleteDialog } from './delete-dialog.tsx';
import { useAttributeValues } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes.ts'
import { MutateDrawer } from './mutate-drawer.tsx'

type DialogProps = {
  attributeId: string
}

export function Dialogs({attributeId}: DialogProps) {
  const { open, setOpen, currentRow, setCurrentRow } = useAttributeValues()
  const queryClient = useQueryClient()

  return (
    <>
      <MutateDrawer
        key='attribute-value-create'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
        attributeId={attributeId}
      />

      {currentRow && (
        <>

          <MutateDrawer
            key={`attribute-value-update-${currentRow.id}`}
            open={open === 'edit'}
            onOpenChange={() => {
              setOpen('edit')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
            attributeId={attributeId}
          />


          <DeleteDialog
            key={`attribute-value-delete-${currentRow.id}`}
            open={open === 'delete'}
            onOpenChange={() => {
              setOpen('delete')
              setTimeout(async () => {
                setCurrentRow(null)
                await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
              }, 500)
            }}
            currentRow={currentRow}
            attributeId={attributeId}
          />
        </>
      )}
    </>
  )
}
