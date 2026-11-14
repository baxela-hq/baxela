import {
  Archive,
  KeyRound,
  ListTree,
  Pencil,
  Send,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { hasPermission } from '@/shared/hooks/use-permissions'
import { Locales } from '../data/routes'
import { type WebhookEndpoint } from '../data/schema'

type RowActionsProps = {
  endpoint: WebhookEndpoint
  onDeliveries: () => void
  onEdit: () => void
  onTest: () => void
  onRegenerate: () => void
  onArchive: () => void
}

/**
 * Row actions, each gated on its own permission — the same
 * failed-jobs pattern: page viewers see/read, writers act.
 */
export function RowActions({
  endpoint,
  onDeliveries,
  onEdit,
  onTest,
  onRegenerate,
  onArchive,
}: RowActionsProps) {
  const { tAction } = useAppTranslation(Locales.WEBHOOK)
  const canUpdate = hasPermission('webhooks.admin.endpoints.update')
  const canRegenerate = hasPermission(
    'webhooks.admin.endpoints.regenerate-secret'
  )
  const canArchive = hasPermission('webhooks.admin.endpoints.delete')
  const canTest = hasPermission('webhooks.admin.endpoints.test')

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant='ghost' className='size-8 p-0'>
          <span className='sr-only'>{tAction('open_menu')}</span>
          …
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end'>
        <DropdownMenuLabel>{endpoint.name}</DropdownMenuLabel>
        <DropdownMenuItem onClick={onDeliveries}>
          <ListTree className='mr-2 size-4' />
          {tAction('deliveries')}
        </DropdownMenuItem>
        {canTest && (
          <DropdownMenuItem onClick={onTest}>
            <Send className='mr-2 size-4' />
            {tAction('send_test')}
          </DropdownMenuItem>
        )}
        {canUpdate && (
          <DropdownMenuItem onClick={onEdit}>
            <Pencil className='mr-2 size-4' />
            {tAction('edit')}
          </DropdownMenuItem>
        )}
        {canRegenerate && (
          <DropdownMenuItem onClick={onRegenerate}>
            <KeyRound className='mr-2 size-4' />
            {tAction('regenerate_secret')}
          </DropdownMenuItem>
        )}
        {canArchive && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              className='text-destructive'
              onClick={onArchive}
            >
              <Archive className='mr-2 size-4' />
              {tAction('archive')}
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
