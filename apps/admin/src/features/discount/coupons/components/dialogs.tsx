import { DeleteDialog } from './delete-dialog.tsx';
import { useCoupons } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes'
import { MutateDrawer } from '../components/mutate-drawer'


export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useCoupons()
  const queryClient = useQueryClient()

  return (
    <>
      <MutateDrawer
        key='coupon-create'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
      />

      {currentRow && (
        <>
          <MutateDrawer
            key={`coupon-update-${currentRow.id}`}
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
            key={`coupon-delete-${currentRow.id}`}
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
