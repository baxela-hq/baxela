import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { type Table } from '@tanstack/react-table'
import { Trash2 } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { DataTableBulkActions as BulkActionsToolbar } from '@/components/data-table'
import { FeatureRoutes, Locales } from '../data/routes.ts'
import { MultiDeleteDialog } from './multi-delete-dialog.tsx'

type DataTableBulkActionsProps<TData> = {
  table: Table<TData>
}

export function DataTableBulkActions<TData>({
  table,
}: DataTableBulkActionsProps<TData>) {
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)
  const queryClient = useQueryClient()
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)

  return (
    <>
      <BulkActionsToolbar table={table}>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='destructive'
              size='icon'
              onClick={() => setShowDeleteConfirm(true)}
              className='size-8'
              aria-label={t('dialog.bulk_delete.delete-selected-items')}
              title={t('dialog.bulk_delete.delete-selected-items')}
            >
              <Trash2 />
              <span className='sr-only'>
                {t('dialog.bulk_delete.delete-selected-items')}
              </span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>{t('dialog.bulk_delete.delete-selected-items')}</p>
          </TooltipContent>
        </Tooltip>
      </BulkActionsToolbar>

      <MultiDeleteDialog
        table={table}
        open={showDeleteConfirm}
        onOpenChange={(open) => {
          setShowDeleteConfirm(open)
          setTimeout(async () => {
            await queryClient.invalidateQueries({
              queryKey: [FeatureRoutes.CACHE_KEY],
            })
          }, 700)
        }}
      />
    </>
  )
}
