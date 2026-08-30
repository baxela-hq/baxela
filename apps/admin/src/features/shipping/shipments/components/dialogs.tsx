import { useShipments } from './provider.tsx';
import { MutateDrawer } from '../components/mutate-drawer'


export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useShipments()

  return (
    <>
      <MutateDrawer
        key='shipment-create'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
      />

      {currentRow && (
        <MutateDrawer
          key={`shipment-update-${currentRow.id}`}
          open={open === 'edit'}
          onOpenChange={() => {
            setOpen('edit')
            setTimeout(() => {
              setCurrentRow(null)
            }, 500)
          }}
          currentRow={currentRow}
        />
      )}
    </>
  )
}
