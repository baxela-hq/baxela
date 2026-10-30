import { MutateDrawer } from './mutate-drawer'
import { useCurrenciesDialog } from './provider'

export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useCurrenciesDialog()

  return (
    <>
      {/* create — no currentRow */}
      <MutateDrawer
        key='currency-create'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
      />

      {currentRow && (
        <MutateDrawer
          key={`currency-update-${currentRow.id}`}
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
