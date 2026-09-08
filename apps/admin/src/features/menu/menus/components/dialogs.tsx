import { DeleteDialog } from './delete-dialog.tsx';
import { useMenus } from './provider.tsx';
import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes.ts'
import { MutateDrawer } from './mutate-drawer.tsx'
import { SortDialog } from '@/features/menu/menu-links/components/sort-dialog.tsx'


export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = useMenus()
  const queryClient = useQueryClient()

  return (
    <>
      <MutateDrawer
        key='menu-create'
        open={open === 'add'}
        onOpenChange={() => setOpen('add')}
      />

      {currentRow && (
        <>

          <MutateDrawer
            key={`menu-update-${currentRow.id}`}
            open={open === 'edit'}
            onOpenChange={() => {
              setOpen('edit')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            currentRow={currentRow}
          />

          <SortDialog
            key={`menu-sort-${currentRow.id}`}
            open={open === 'sort'}
            onOpenChange={() => {
              setOpen('sort')
              setTimeout(() => {
                setCurrentRow(null)
              }, 500)
            }}
            menuId={currentRow.id.toString()}
          />

          <DeleteDialog
            key={`menu-delete-${currentRow.id}`}
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
