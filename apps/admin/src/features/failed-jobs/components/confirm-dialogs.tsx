import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'
import { useForgetFailedJob, useRetryFailedJob } from '../hooks/use-failed-job-mutations'

type ConfirmDialogProps = {
  job: { uuid: string; job: string | null } | null
  onClose: () => void
}

/**
 * Confirmation dialogs for the two mutating actions (gap-B3 D4): retry
 * re-queues for another attempt and may repeat the job's side effects;
 * forget permanently deletes the record.
 */
export function RetryDialog({ job, onClose }: ConfirmDialogProps) {
  const { tLabel, tMessage } = useAppTranslation(Locales.FAILED_JOBS)
  const retry = useRetryFailedJob()

  return (
    <Dialog open={job !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='sm:max-w-lg'>
        <DialogHeader>
          <DialogTitle>{tLabel('retry-title')}</DialogTitle>
          <DialogDescription>{tMessage('retry-warning')}</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant='outline' onClick={onClose}>
            {tLabel('cancel')}
          </Button>
          <Button
            onClick={() => {
              if (job) retry.mutate(job.uuid)
              onClose()
            }}
            disabled={retry.isPending}
          >
            {tLabel('retry')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function ForgetDialog({ job, onClose }: ConfirmDialogProps) {
  const { tLabel, tMessage } = useAppTranslation(Locales.FAILED_JOBS)
  const forget = useForgetFailedJob()

  return (
    <Dialog open={job !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='sm:max-w-lg'>
        <DialogHeader>
          <DialogTitle>{tLabel('forget-title')}</DialogTitle>
          <DialogDescription>{tMessage('forget-warning')}</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant='outline' onClick={onClose}>
            {tLabel('cancel')}
          </Button>
          <Button
            variant='destructive'
            onClick={() => {
              if (job) forget.mutate(job.uuid)
              onClose()
            }}
            disabled={forget.isPending}
          >
            {tLabel('forget')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
