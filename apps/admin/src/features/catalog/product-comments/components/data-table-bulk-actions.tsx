import { useState } from 'react'
import { type Table } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { Ban, Check, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { DataTableBulkActions as BulkActionsToolbar } from '@/components/data-table'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { MultiDeleteDialog } from './multi-delete-dialog'
import { FeatureRoutes, Locales } from '../data/routes'
import { type ProductComment } from '../data/schema'
import {
  useBulkUpdateProductCommentStatus,
} from '../hooks/use-product-comment-mutations'

type DataTableBulkActionsProps<TData> = {
  table: Table<TData>
}

export function DataTableBulkActions<TData>({
  table,
}: DataTableBulkActionsProps<TData>) {
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)
  const queryClient = useQueryClient()

  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT_COMMENT)

  const bulkUpdateStatus = useBulkUpdateProductCommentStatus()

  const selectedComments = table
    .getFilteredSelectedRowModel()
    .rows.map((row) => row.original as ProductComment)

  const handleBulkStatus = (
    status: 'approved' | 'rejected',
    actionLabel: string
  ) => {
    toast.promise(
      bulkUpdateStatus.mutateAsync({ comments: selectedComments, status }),
      {
        loading: tMessage('info.processing'),
        success: () => {
          table.resetRowSelection()
          return tMessage('success.default', {
            name: tLabel('comments'),
            action: actionLabel,
          })
        },
        error: tMessage('error.general'),
      }
    )
  }

  return (
    <>
      <BulkActionsToolbar table={table}>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='outline'
              size='icon'
              onClick={() => handleBulkStatus('approved', tLabel('approved'))}
              className='size-8'
              aria-label={tLabel('approve')}
              title={tLabel('approve')}
            >
              <Check />
              <span className='sr-only'>{tLabel('approve')}</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>{tLabel('approve')}</p>
          </TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='outline'
              size='icon'
              onClick={() => handleBulkStatus('rejected', tLabel('rejected'))}
              className='size-8'
              aria-label={tLabel('reject')}
              title={tLabel('reject')}
            >
              <Ban />
              <span className='sr-only'>{tLabel('reject')}</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>{tLabel('reject')}</p>
          </TooltipContent>
        </Tooltip>

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
