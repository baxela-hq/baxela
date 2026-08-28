import { MutateDrawer } from '../components/mutate-drawer';
import { useOrders } from './provider.tsx';

type DialogsProps = {
  onUpdate?: () => void
}


export function Dialogs({ onUpdate }: DialogsProps) {
  const { open, setOpen, currentRow, setCurrentRow } = useOrders()

  return (
    <>
      {currentRow && (
        <>
          <MutateDrawer
            key={`order-update-${currentRow.id}`}
            open={open === 'edit'}
            onOpenChange={() => {
              setOpen('edit')
              setTimeout(() => {
                setCurrentRow(null)
                onUpdate?.()
              }, 500)
            }}
            currentRow={currentRow}
          />
        </>
      )}
    </>
  )
}
