import { Eye } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time'
import { useFailedJob } from '../hooks/use-failed-jobs'
import { Locales } from '../data/routes'

type ViewDialogProps = {
  uuid: string | null
  onClose: () => void
}

/**
 * Detail dialog: fetches the full row — complete exception plus the redacted
 * payload (serialized command replaced, secret-ish keys masked server-side,
 * gap-B3 D3). Everything renders as escaped text.
 */
export function ViewDialog({ uuid, onClose }: ViewDialogProps) {
  const { tLabel } = useAppTranslation(Locales.FAILED_JOBS)
  const { formatDateTime } = useFormatDateTime()
  const { data: job, isLoading } = useFailedJob(uuid)

  return (
    <Dialog open={uuid !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='sm:max-w-3xl'>
        <DialogHeader>
          <DialogTitle className='flex items-center gap-2'>
            <Eye className='size-4' />
            {tLabel('failed-job')}
          </DialogTitle>
          <DialogDescription>
            {job?.failed_at ? formatDateTime(job.failed_at) : ''}
          </DialogDescription>
        </DialogHeader>

        {isLoading && <Skeleton className='h-48 w-full' />}

        {job && (
          <div className='space-y-4 overflow-y-auto'>
            <dl className='grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm'>
              <dt className='text-muted-foreground'>{tLabel('job')}</dt>
              <dd className='break-all'>{job.job ?? '—'}</dd>
              <dt className='text-muted-foreground'>{tLabel('queue')}</dt>
              <dd>{job.queue}</dd>
              <dt className='text-muted-foreground'>{tLabel('connection')}</dt>
              <dd>{job.connection}</dd>
              <dt className='text-muted-foreground'>{tLabel('uuid')}</dt>
              <dd className='break-all'>{job.uuid}</dd>
            </dl>

            <div>
              <div className='mb-1 text-sm text-muted-foreground'>
                {tLabel('exception')}
              </div>
              <pre
                className='max-h-48 overflow-auto rounded-md border bg-muted/50 p-3 text-xs leading-relaxed whitespace-pre-wrap'
                dir='ltr'
              >
                {job.exception}
              </pre>
            </div>

            <div>
              <div className='mb-1 text-sm text-muted-foreground'>
                {tLabel('payload')}
              </div>
              <pre
                className='max-h-72 overflow-auto rounded-md border bg-muted/50 p-3 text-xs leading-relaxed'
                dir='ltr'
              >
                {JSON.stringify(job.payload ?? {}, null, 2)}
              </pre>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
