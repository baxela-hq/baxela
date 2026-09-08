import { DeleteDialog } from './delete-dialog.tsx';
import { useMenuLinks } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes.ts'
import { MutateDrawer } from './mutate-drawer.tsx'
import { SortDialog } from './sort-dialog.tsx'

type DialogProps= {
  menuId: string
}
export function Dialogs({menuId}: DialogProps) {
  const { open, setOpen, currentRow, setCurrentRow } = useMenuLinks()
  const queryClient = useQueryClient()

  return (
    <>
      <MutateDrawer
        key='menu-link-create'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
        menuId={menuId}
      />

      {currentRow && (
        <>

          <MutateDrawer
            key={`menu-link-update-${currentRow.id}`}
            open={open === 'edit'}
            onOpenChange={() => {
              setOpen('edit')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
            menuId={menuId}
          />


          <DeleteDialog
            key={`menu-link-delete-${currentRow.id}`}
            open={open === 'delete'}
            onOpenChange={() => {
              setOpen('delete')
              setTimeout(async () => {
                setCurrentRow(null)
                await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
              }, 500)
            }}
            currentRow={currentRow}
            menuId={menuId}
          />
        </>
      )}

      <SortDialog
        key={`menu-links-sort-${menuId}`}
        open={open === 'sort'}
        onOpenChange={() => setOpen('sort')}
        menuId={menuId}
      />
    </>
  )
}
