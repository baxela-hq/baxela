import { Eye, RotateCcw, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { hasPermission } from '@/shared/hooks/use-permissions'
import { Locales } from '../data/routes'

type RowActionsProps = {
  onView: () => void
  onRetry: () => void
  onForget: () => void
}

/**
 * Row actions, each gated on its own permission (gap-B3 D2): view always
 * available to page viewers, retry/delete only for their holders.
 */
export function RowActions({ onView, onRetry, onForget }: RowActionsProps) {
  const { tAction } = useAppTranslation(Locales.FAILED_JOBS)
  const canRetry = hasPermission('core.admin.failed-jobs.retry')
  const canForget = hasPermission('core.admin.failed-jobs.delete')

  return (
    <div className='flex space-x-1'>
      <Button variant='ghost' size='icon' className='size-8' onClick={onView}>
        <Eye className='size-4' />
        <span className='sr-only'>{tAction('view')}</span>
      </Button>
      {canRetry && (
        <Button variant='ghost' size='icon' className='size-8' onClick={onRetry}>
          <RotateCcw className='size-4' />
          <span className='sr-only'>{tAction('retry')}</span>
        </Button>
      )}
      {canForget && (
        <Button
          variant='ghost'
          size='icon'
          className='size-8 text-destructive'
          onClick={onForget}
        >
          <Trash2 className='size-4' />
          <span className='sr-only'>{tAction('forget')}</span>
        </Button>
      )}
    </div>
  )
}
