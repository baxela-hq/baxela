import { useMemo, useState } from 'react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Loader2, RefreshCw, ShieldAlert, ShieldCheck } from 'lucide-react'
import {
  extensionToggleUrl,
} from '../api/extensions.api'
import {
  KNOWN_UPDATE_STATES,
  isStaleSnapshot,
  type CredentialState,
  type DriverCredentialStates,
  type Extension,
  type ExtensionUpdateEntry,
  type ExtensionUpdatesEnvelope,
} from '../data/extensions.types'
import { Locales } from '../data/routes'
import {
  useDriverCredentials,
  useExtensionUpdates,
  useExtensionsList,
} from '../hooks/use-extensions'
import {
  useDeleteDriverCredential,
  useToggleExtension,
  useUpdateDriverCredentials,
} from '../hooks/use-extensions-mutations'

/**
 * The Extensions tab (marketplace P5.4) — the admin surface for three
 * frozen backend capabilities, in three deliberately independent
 * sections: extension toggles (P5.1), payment-driver credential states
 * (P5.2) and marketplace update availability (P5.3). A failure in one
 * section never hides the others; each owns its loading/empty/error
 * rendering. Extensions and configurable drivers are independent
 * collections — never joined (F4).
 */
export function ExtensionsTab() {
  return (
    <div className='grid gap-6'>
      <ExtensionsSection />
      <CredentialsSection />
      <UpdatesSection />
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Extensions (P5.1)                                                   */
/* ------------------------------------------------------------------ */

function ExtensionsSection() {
  const { t } = useAppTranslation(Locales.SETTING)
  const { tAction, tStatus, tHelpText } = useAppTranslation(
    Locales.PAYMENT_CREDENTIALS
  )
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { data, isLoading, isError, error, refetch } = useExtensionsList()
  const toggle = useToggleExtension()

  // The truthful unconditional worker caveat (F2): shown after every
  // successful toggle until the next list refresh. This is NOT a
  // failure notice — persisted state did change.
  const [showWorkerCaveat, setShowWorkerCaveat] = useState(false)
  const [confirm, setConfirm] = useState<{ slug: string; enable: boolean } | null>(
    null
  )

  const onToggle = async (slug: string, enable: boolean) => {
    setConfirm(null)
    // Single-flight: the button is disabled while pending, so this
    // observer never carries two overlapping mutations (F6 invariant).
    await toggle.run({ slug, enable })
    setShowWorkerCaveat(true)
  }

  return (
    <Card>
      <CardHeader className='flex flex-row items-center justify-between'>
        <CardTitle>{t('extensions.title')}</CardTitle>
        <Button
          variant='ghost'
          size='icon'
          onClick={() => refetch()}
          aria-label={tAction('refresh')}
        >
          <RefreshCw />
        </Button>
      </CardHeader>
      <CardContent className='grid gap-3'>
        {isLoading && <SkeletonWidget />}

        {isError && (
          <Alert variant='destructive'>
            <ShieldAlert />
            <AlertTitle>{tMessage('error.general')}</AlertTitle>
            <AlertDescription>
              {error instanceof Error ? error.message : tMessage('error.general')}
            </AlertDescription>
          </Alert>
        )}

        {!isLoading && !isError && (data?.data ?? []).length === 0 && (
          <p className='text-muted-foreground text-sm'>
            {t('extensions.empty')}
          </p>
        )}

        {showWorkerCaveat && (
          <Alert>
            <ShieldAlert />
            <AlertTitle>{t('extensions.worker_caveat_title')}</AlertTitle>
            <AlertDescription>{t('extensions.worker_caveat_body')}</AlertDescription>
          </Alert>
        )}

        {(data?.data ?? []).map((extension) => (
          <ExtensionRow
            key={extension.slug}
            extension={extension}
            pending={toggle.mutation.isPending}
            pendingSlug={toggle.mutation.variables?.slug}
            onOpenConfirm={setConfirm}
            statusLabel={(reason) => tStatus(reason)}
            enabledLabel={tStatus('enabled')}
            disabledLabel={tStatus('disabled')}
            notInstalledLabel={tStatus('not_installed')}
            helpText={tHelpText('toggle_hint')}
          />
        ))}

        <AlertDialog open={confirm !== null}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>
                {t(
                  confirm?.enable
                    ? 'extensions.confirm_enable_title'
                    : 'extensions.confirm_disable_title',
                  { name: confirm?.slug ?? '' }
                )}
              </AlertDialogTitle>
              <AlertDialogDescription>
                {t(
                  confirm?.enable
                    ? 'extensions.confirm_enable_body'
                    : 'extensions.confirm_disable_body'
                )}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel onClick={() => setConfirm(null)}>
                {tAction('cancel')}
              </AlertDialogCancel>
              <AlertDialogAction
                onClick={() => {
                  if (confirm) onToggle(confirm.slug, confirm.enable)
                }}
              >
                {tAction('confirm')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </CardContent>
    </Card>
  )
}

function ExtensionRow({
  extension,
  pending,
  pendingSlug,
  onOpenConfirm,
  statusLabel,
  enabledLabel,
  disabledLabel,
  notInstalledLabel,
  helpText,
}: {
  extension: Extension
  pending: boolean
  pendingSlug?: string
  onOpenConfirm: (c: { slug: string; enable: boolean } | null) => void
  statusLabel: (reason: string) => string
  enabledLabel: string
  disabledLabel: string
  notInstalledLabel: string
  helpText: string
}) {
  const urlUsable = extensionToggleUrl(extension.slug, 'enable') !== null
  const rowPending = pending && pendingSlug === extension.slug

  return (
    <div className='flex items-center justify-between gap-3 rounded-md border p-3'>
      <div className='grid gap-0.5'>
        <span className='font-mono text-sm'>{extension.slug}</span>
        <span className='text-muted-foreground text-xs'>
          {extension.version ?? notInstalledLabel}
          {extension.skip_reason !== null && (
            <> · {statusLabel(extension.skip_reason)}</>
          )}
        </span>
      </div>
      <div className='flex items-center gap-2'>
        <Badge variant={extension.enabled ? 'default' : 'secondary'}>
          {extension.enabled ? enabledLabel : disabledLabel}
        </Badge>
        <Button
          size='sm'
          variant={extension.enabled ? 'destructive' : 'default'}
          disabled={pending || !urlUsable}
          title={helpText}
          onClick={() =>
            onOpenConfirm({ slug: extension.slug, enable: !extension.enabled })
          }
        >
          {rowPending && <Loader2 className='animate-spin' />}
          {extension.enabled ? (
            <ShieldAlert />
          ) : (
            <ShieldCheck />
          )}
        </Button>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Credentials (P5.2)                                                  */
/* ------------------------------------------------------------------ */

function CredentialsSection() {
  const { t } = useAppTranslation(Locales.SETTING)
  const { tAction, tStatus, tHelpText, tPlaceHolder } = useAppTranslation(
    Locales.PAYMENT_CREDENTIALS
  )
  const { data, isLoading, isError, error, refetch } = useDriverCredentials()
  const update = useUpdateDriverCredentials()
  const remove = useDeleteDriverCredential()

  const drivers = data?.data ?? []

  return (
    <Card>
      <CardHeader className='flex flex-row items-center justify-between'>
        <CardTitle>{t('credentials.title')}</CardTitle>
        <Button
          variant='ghost'
          size='icon'
          onClick={() => refetch()}
          aria-label={tAction('refresh')}
        >
          <RefreshCw />
        </Button>
      </CardHeader>
      <CardContent className='grid gap-4'>
        {isLoading && <SkeletonWidget />}

        {isError && (
          <Alert variant='destructive'>
            <ShieldAlert />
            <AlertTitle>{tStatus('load_failed')}</AlertTitle>
            <AlertDescription>
              {error instanceof Error ? error.message : tStatus('load_failed')}
            </AlertDescription>
          </Alert>
        )}

        {!isLoading && !isError && drivers.length === 0 && (
          <p className='text-muted-foreground text-sm'>
            {t('credentials.empty')}
          </p>
        )}

        {drivers.map((driver) => (
          <DriverCard
            key={driver.driver}
            driver={driver}
            updatePending={update.mutation.isPending}
            deletePending={remove.mutation.isPending}
            onSave={async (credentials) => {
              // Blank fields are stripped before this point — an empty
              // PATCH payload is never sent. Single-flight: disabled
              // while pending (F6 invariant).
              await update.run({ driver: driver.driver, credentials })
            }}
            onDelete={async (key) => {
              await remove.run({ driver: driver.driver, key })
            }}
            stateLabel={(s) => tStatus(s)}
            saveLabel={tAction('save')}
            deleteLabel={tAction('remove')}
            cancelLabel={tAction('cancel')}
            confirmLabel={tAction('confirm')}
            placeholder={tPlaceHolder('credential_value')}
            configuredHelp={tHelpText('configured')}
            unconfiguredHelp={tHelpText('unconfigured')}
            envFallbackHelp={tHelpText('env_fallback')}
            undecryptableHelp={tHelpText('undecryptable')}
          />
        ))}
      </CardContent>
    </Card>
  )
}

function DriverCard({
  driver,
  updatePending,
  deletePending,
  onSave,
  onDelete,
  stateLabel,
  saveLabel,
  deleteLabel,
  cancelLabel,
  confirmLabel,
  placeholder,
  configuredHelp,
  unconfiguredHelp,
  envFallbackHelp,
  undecryptableHelp,
}: {
  driver: DriverCredentialStates
  updatePending: boolean
  deletePending: boolean
  onSave: (credentials: Record<string, string>) => Promise<void>
  onDelete: (key: string) => Promise<void>
  stateLabel: (s: string) => string
  saveLabel: string
  deleteLabel: string
  cancelLabel: string
  confirmLabel: string
  placeholder: string
  configuredHelp: string
  unconfiguredHelp: string
  envFallbackHelp: string
  undecryptableHelp: string
}) {
  // Local, ephemeral input state — the only place entered plaintext
  // lives. Cleared on success; preserved on failure until the user
  // edits/clears/unmounts (acknowledged in the plan, F6.4).
  const [values, setValues] = useState<Record<string, string>>({})
  const [confirmDelete, setConfirmDelete] = useState<string | null>(null)

  const draft = Object.fromEntries(
    Object.entries(values).filter(([, v]) => v.trim() !== '')
  )
  const hasDraft = Object.keys(draft).length > 0

  const stateHelp = (s: CredentialState): string => {
    switch (s) {
      case 'stored':
        return configuredHelp
      case 'env':
        return envFallbackHelp
      case 'none':
        return unconfiguredHelp
      case 'undecryptable':
        return undecryptableHelp
    }
  }

  return (
    <div className='grid gap-3 rounded-md border p-3'>
      <div className='flex items-center justify-between'>
        <span className='font-mono text-sm font-medium'>{driver.driver}</span>
        <Badge variant={driver.is_configured ? 'default' : 'destructive'}>
          {stateLabel(driver.is_configured ? 'configured' : 'unconfigured')}
        </Badge>
      </div>

      <Separator />

      {Object.entries(driver.keys).map(([key, state]) => (
        <div key={key} className='grid gap-1.5'>
          <div className='flex items-center justify-between gap-2'>
            <Label htmlFor={`cred-${driver.driver}-${key}`} className='font-mono text-xs'>
              {key}
            </Label>
            <div className='flex items-center gap-1.5'>
              <Badge
                variant={
                  state === 'undecryptable'
                    ? 'destructive'
                    : state === 'none'
                      ? 'outline'
                      : 'secondary'
                }
              >
                {stateLabel(state)}
              </Badge>
              {state === 'stored' && (
                <Button
                  size='sm'
                  variant='ghost'
                  disabled={updatePending || deletePending}
                  onClick={() => setConfirmDelete(key)}
                >
                  {deletePending && confirmDelete === key && (
                    <Loader2 className='animate-spin' />
                  )}
                  {deleteLabel}
                </Button>
              )}
            </div>
          </div>
          {/* An empty password input — never a fabricated placeholder
              standing in for an existing secret (plan §7). */}
          <Input
            id={`cred-${driver.driver}-${key}`}
            type='password'
            autoComplete='new-password'
            placeholder={placeholder}
            value={values[key] ?? ''}
            onChange={(e) =>
              setValues((prev) => ({ ...prev, [key]: e.target.value }))
            }
          />
          <p className='text-muted-foreground text-xs'>{stateHelp(state)}</p>
        </div>
      ))}

      <div className='flex justify-end'>
        <Button
          size='sm'
          disabled={!hasDraft || updatePending || deletePending}
          onClick={async () => {
            await onSave(draft)
            setValues({})
          }}
        >
          {updatePending && <Loader2 className='animate-spin' />}
          {saveLabel}
        </Button>
      </div>

      <AlertDialog open={confirmDelete !== null}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{deleteLabel} — {confirmDelete}</AlertDialogTitle>
            <AlertDialogDescription>
              {envFallbackHelp}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => setConfirmDelete(null)}>
              {deleteLabel === '' ? 'cancel' : cancelLabel}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                const key = confirmDelete
                setConfirmDelete(null)
                if (key) void onDelete(key)
              }}
            >
              {confirmLabel}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Updates (P5.3)                                                      */
/* ------------------------------------------------------------------ */

function UpdatesSection() {
  const { t } = useAppTranslation(Locales.SETTING)
  const { tStatus, tHelpText, tAction } = useAppTranslation(
    Locales.PAYMENT_CREDENTIALS
  )
  const { data, isLoading, isError, error, refetch } = useExtensionUpdates()
  const envelope: ExtensionUpdatesEnvelope | undefined = data?.data

  const stale = envelope !== undefined && isStaleSnapshot(envelope)
  const entries = useMemo(() => Object.entries(envelope?.data ?? {}), [envelope])

  return (
    <Card>
      <CardHeader className='flex flex-row items-center justify-between'>
        <CardTitle>{t('updates.title')}</CardTitle>
        <Button
          variant='ghost'
          size='icon'
          onClick={() => refetch()}
          aria-label={tAction('refresh')}
        >
          <RefreshCw />
        </Button>
      </CardHeader>
      <CardContent className='grid gap-3'>
        {isLoading && <SkeletonWidget />}

        {isError && (
          <Alert variant='destructive'>
            <ShieldAlert />
            <AlertTitle>{tStatus('load_failed')}</AlertTitle>
            <AlertDescription>
              {error instanceof Error ? error.message : tStatus('load_failed')}
            </AlertDescription>
          </Alert>
        )}

        {envelope?.availability === 'marketplace_unconfigured' && (
          <p className='text-muted-foreground text-sm'>
            {tHelpText('marketplace_unconfigured')}
          </p>
        )}

        {envelope?.availability === 'marketplace_unreachable' && (
          <Alert variant='destructive'>
            <ShieldAlert />
            <AlertTitle>{tHelpText('marketplace_unreachable')}</AlertTitle>
            {stale && envelope.generated_at !== null && (
              <AlertDescription>
                {tHelpText('stale_generated_at', {
                  time: envelope.generated_at,
                })}
              </AlertDescription>
            )}
          </Alert>
        )}

        {envelope?.availability === 'marketplace_error' && (
          <Alert variant='destructive'>
            <ShieldAlert />
            <AlertTitle>{tHelpText('marketplace_error')}</AlertTitle>
            {stale && envelope.generated_at !== null && (
              <AlertDescription>
                {tHelpText('stale_generated_at', {
                  time: envelope.generated_at,
                })}
              </AlertDescription>
            )}
          </Alert>
        )}

        {entries.length === 0 &&
          envelope?.availability === 'ok' &&
          !envelope.cached && (
            <p className='text-muted-foreground text-sm'>
              {t('updates.empty')}
            </p>
          )}

        {entries.map(([slug, entry]) => (
          <UpdateRow
            key={slug}
            slug={slug}
            entry={entry}
            stateLabel={tStatus}
            yankedHelp={tHelpText('current_yanked')}
          />
        ))}
      </CardContent>
    </Card>
  )
}

function UpdateRow({
  slug,
  entry,
  stateLabel,
  yankedHelp,
}: {
  slug: string
  entry: ExtensionUpdateEntry
  stateLabel: (key: string) => string
  yankedHelp: string
}) {
  const known = (KNOWN_UPDATE_STATES as readonly string[]).includes(entry.state)

  return (
    <div className='flex items-center justify-between gap-3 rounded-md border p-3'>
      <div className='grid gap-0.5'>
        <span className='font-mono text-sm'>{slug}</span>
        {entry.latest !== null && (
          <span className='text-muted-foreground text-xs'>{entry.latest}</span>
        )}
      </div>
      <div className='flex items-center gap-2'>
        {entry.current_yanked && (
          <Badge variant='destructive' title={yankedHelp}>
            {stateLabel('current_yanked')}
          </Badge>
        )}
        <Badge
          variant={
            !known
              ? 'outline'
              : entry.state === 'update_available'
                ? 'default'
                : entry.state === 'package_yanked' || entry.state === 'unknown_package'
                  ? 'destructive'
                  : 'secondary'
          }
        >
          {/* Unknown marketplace states render as their raw value —
              never an up-to-date badge (plan F5). */}
          {known ? stateLabel(entry.state) : entry.state}
        </Badge>
      </div>
    </div>
  )
}
