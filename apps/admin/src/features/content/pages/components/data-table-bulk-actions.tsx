import { useState } from 'react'
import { type Table } from '@tanstack/react-table'
import { Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { DataTableBulkActions as BulkActionsToolbar } from '@/components/data-table'
import { MultiDeleteDialog } from './multi-delete-dialog.tsx'
import { Locales } from '@/features/catalog/products/data/routes'
import { useAppTranslation } from '@/hooks/useAppTranslation';

type DataTableBulkActionsProps<TData> = {
  table: Table<TData>
}

export function DataTableBulkActions<TData>({
  table,
}: DataTableBulkActionsProps<TData>) {
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)

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
              <span className='sr-only'>{t('dialog.bulk_delete.delete-selected-items')}</span>
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
        onOpenChange={setShowDeleteConfirm}
      />
    </>
  )
}
