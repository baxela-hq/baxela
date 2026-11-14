import { Loader2, RotateCcw } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { hasPermission } from '@/shared/hooks/use-permissions'
import { Locales } from '../data/routes'
import { type WebhookEndpoint } from '../data/schema'
import { useWebhookDeliveries } from '../hooks/use-webhooks'
import { useRetryWebhookDelivery } from '../hooks/use-webhook-mutations'

const STATUS_VARIANT: Record<string, string> = {
  succeeded: 'default',
  pending: 'secondary',
  processing: 'secondary',
  failed_retryable: 'destructive',
  failed_permanent: 'destructive',
}

/**
 * One endpoint's delivery log: response status, sanitized snippet,
 * retry for failures. Sanitized server-side — full bodies and secrets
 * never reach this table.
 */
export function DeliveriesDialog({
  endpoint,
  onClose,
}: {
  endpoint: WebhookEndpoint | null
  onClose: () => void
}) {
  const { tLabel, tStatus, tHelpText, tAction } = useAppTranslation(
    Locales.WEBHOOK
  )
  const { data, isLoading, refetch, isFetching } = useWebhookDeliveries(
    endpoint?.id ?? null
  )
  const retry = useRetryWebhookDelivery()
  const canRetry = hasPermission('webhooks.admin.deliveries.retry')

  return (
    <Dialog open={endpoint !== null} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className='max-h-[85vh] overflow-y-auto sm:max-w-3xl'>
        <DialogHeader>
          <DialogTitle>
            {tLabel('deliveries-title')}
            {endpoint !== null && (
              <span className='text-muted-foreground font-normal'>
                {' '}
                — {endpoint.name}
              </span>
            )}
          </DialogTitle>
          <DialogDescription>{tHelpText('deliveries_help')}</DialogDescription>
        </DialogHeader>

        <div className='flex justify-end'>
          <Button
            variant='ghost'
            size='sm'
            disabled={isFetching}
            onClick={() => refetch()}
          >
            {isFetching && <Loader2 className='animate-spin' />}
            {tAction('refresh')}
          </Button>
        </div>

        {isLoading && (
          <div className='flex justify-center py-8'>
            <Loader2 className='animate-spin' />
          </div>
        )}

        {!isLoading && (data?.data ?? []).length === 0 && (
          <p className='text-muted-foreground py-8 text-center text-sm'>
            {tHelpText('no_deliveries')}
          </p>
        )}

        {(data?.data ?? []).length > 0 && (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{tLabel('event_type')}</TableHead>
                <TableHead>{tLabel('status')}</TableHead>
                <TableHead>{tLabel('response_status')}</TableHead>
                <TableHead>{tLabel('attempt_count')}</TableHead>
                <TableHead>{tLabel('created_at')}</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {(data?.data ?? []).map((delivery) => (
                <TableRow key={delivery.id}>
                  <TableCell className='font-mono text-xs'>
                    {delivery.event_type}
                  </TableCell>
                  <TableCell>
                    <Badge variant={(STATUS_VARIANT[delivery.status] ??
                      'outline') as 'default' | 'secondary' | 'destructive' | 'outline'}>
                      {tStatus(delivery.status)}
                    </Badge>
                    {delivery.error_category !== null && (
                      <span className='text-muted-foreground ml-1 text-xs'>
                        {delivery.error_category}
                      </span>
                    )}
                  </TableCell>
                  <TableCell>{delivery.response_status ?? '—'}</TableCell>
                  <TableCell>{delivery.attempt_count}</TableCell>
                  <TableCell className='text-xs'>
                    {delivery.created_at}
                  </TableCell>
                  <TableCell>
                    {canRetry &&
                      (delivery.status === 'failed_retryable' ||
                        delivery.status === 'failed_permanent') && (
                        <Button
                          size='icon'
                          variant='ghost'
                          className='size-8'
                          disabled={retry.isPending}
                          title={delivery.response_snippet ?? undefined}
                          onClick={() => retry.mutate(delivery.id)}
                        >
                          {retry.isPending ? (
                            <Loader2 className='size-4 animate-spin' />
                          ) : (
                            <RotateCcw className='size-4' />
                          )}
                          <span className='sr-only'>{tAction('retry')}</span>
                        </Button>
                      )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </DialogContent>
    </Dialog>
  )
}
