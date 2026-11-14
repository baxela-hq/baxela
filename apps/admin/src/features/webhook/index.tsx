import { useState } from 'react'
import { getRouteApi } from '@tanstack/react-router'
import { Loader2, Plus } from 'lucide-react'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { hasPermission } from '@/shared/hooks/use-permissions'
import { Locales } from './data/routes'
import { type WebhookEndpoint } from './data/schema'
import { useWebhookEndpoints } from './hooks/use-webhooks'
import {
  useArchiveWebhookEndpoint,
  useCreateWebhookEndpoint,
  useRegenerateWebhookSecret,
  useTestWebhookEndpoint,
  useUpdateWebhookEndpoint,
} from './hooks/use-webhook-mutations'
import { DataTable } from './components/data-table'
import { DeliveriesDialog } from './components/deliveries-dialog'
import { EndpointDialog } from './components/endpoint-dialog'
import { SecretDialog } from './components/secret-dialog'

const route = getRouteApi('/_authenticated/webhooks/')

/**
 * The webhook endpoints page (gap-C.2 admin UI): merchants manage where
 * Baxela POSTs signed store events, inspect delivery history and send
 * test events. The backend contracts are frozen — this is purely their
 * presentation layer.
 */
export function Webhooks() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle, tLabel, tHelpText, tAction } = useAppTranslation(
    Locales.WEBHOOK
  )
  const { data, isLoading, isSuccess } = useWebhookEndpoints(search)

  const create = useCreateWebhookEndpoint()
  const update = useUpdateWebhookEndpoint()
  const archive = useArchiveWebhookEndpoint()
  const regenerate = useRegenerateWebhookSecret()
  const test = useTestWebhookEndpoint()

  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<WebhookEndpoint | null>(null)
  const [viewingDeliveries, setViewingDeliveries] =
    useState<WebhookEndpoint | null>(null)
  const [archiving, setArchiving] = useState<WebhookEndpoint | null>(null)
  // The once-only secret, held exactly until the dialog closes.
  const [secret, setSecret] = useState<string | null>(null)

  const canCreate = hasPermission('webhooks.admin.endpoints.create')

  return (
    <>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle('index.title')}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle')}
            </p>
          </div>
          {canCreate && (
            <Button size='sm' onClick={() => setCreating(true)}>
              {create.isPending ? (
                <Loader2 className='animate-spin' />
              ) : (
                <Plus />
              )}
              {tAction('create')}
            </Button>
          )}
        </div>

        {isLoading && <SkeletonWidget />}
        {isSuccess && data && (
          <DataTable
            data={data}
            search={search}
            navigate={navigate}
            onDeliveries={setViewingDeliveries}
            onEdit={setEditing}
            onTest={(endpoint) => test.mutate(endpoint.id)}
            onRegenerate={(endpoint) =>
              regenerate.mutate(endpoint.id, {
                // The once-only secret surfaces immediately.
                onSuccess: (result) => setSecret(result.secret),
              })
            }
            onArchive={setArchiving}
          />
        )}

        <EndpointDialog
          open={creating}
          endpoint={null}
          submitting={create.isPending}
          onClose={() => setCreating(false)}
          onSubmit={(input) => {
            create.mutate(input, {
              // The once-only secret surfaces immediately.
              onSuccess: (result) => setSecret(result.secret),
            })
            setCreating(false)
          }}
        />

        <EndpointDialog
          open={editing !== null}
          endpoint={editing}
          submitting={update.isPending}
          onClose={() => setEditing(null)}
          onSubmit={(input) => {
            if (editing !== null) update.mutate({ id: editing.id, ...input })
            setEditing(null)
          }}
        />

        <DeliveriesDialog
          endpoint={viewingDeliveries}
          onClose={() => setViewingDeliveries(null)}
        />

        <SecretDialog secret={secret} onClose={() => setSecret(null)} />

        <AlertDialog open={archiving !== null}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>
                {tLabel('archive-title')} — {archiving?.name}
              </AlertDialogTitle>
              <AlertDialogDescription>
                {tHelpText('archive_help')}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel onClick={() => setArchiving(null)}>
                {tAction('cancel')}
              </AlertDialogCancel>
              <AlertDialogAction
                onClick={() => {
                  if (archiving) archive.mutate(archiving.id)
                  setArchiving(null)
                }}
              >
                {tAction('confirm')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </>
  )
}
