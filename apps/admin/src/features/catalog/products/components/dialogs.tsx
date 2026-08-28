import { DeleteDialog } from './delete-dialog.tsx';
import { Import } from './import.tsx';
import { useProducts } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes.ts'


export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useProducts()
  const queryClient = useQueryClient()

  return (
    <>
      <Import
        key='product-import'
        open={open === 'import'}
        onOpenChange={() => setOpen('import')}
      />

      {currentRow && (
        <>
          <DeleteDialog
            key={`user-delete-${currentRow.id}`}
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
