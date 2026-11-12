import { useState } from 'react'
import { Loader2, RefreshCw, X } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { hasPermission } from '@/shared/hooks/use-permissions'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { useFxMutations, useFxSettings } from '../hooks/use-fx-sync'

/**
 * The FX rate auto-sync card inside the currencies tab (Part E).
 *
 * Credential handling: the key input is write-only — it never prefills;
 * a blank field is OMITTED from the payload (keeps the stored key),
 * explicit clear sends null. The masked value only acts as a
 * configured-state indicator.
 */
export function FxSyncCard() {
  const { t } = useAppTranslation('core/currency')
  const { query, enabled, keyConfigured, provider, intervalHours, status } =
    useFxSettings()
  const { save, syncNow } = useFxMutations()

  // null = untouched (omit) · ''-handled server-side via null clear
  const [keyInput, setKeyInput] = useState<string | null>(null)
  const [clearKey, setClearKey] = useState(false)
  const [interval, setIntervalHours] = useState<string | null>(null)

  const canSync = hasPermission('core.admin.currencies.sync')

  if (query.isLoading) return null

  const saveSettings = () => {
    const items: ({ name: string; value: string | null })[] = []

    // only include the fields the user actually touched
    if (keyInput !== null && keyInput !== '') {
      items.push({ name: 'fx_api_key', value: keyInput })
    } else if (clearKey) {
      items.push({ name: 'fx_api_key', value: null })
    }

    if (interval !== null) {
      items.push({ name: 'fx_sync_interval_hours', value: interval })
    }

    if (items.length === 0) return

    save.mutate(items)
    setKeyInput(null)
    setClearKey(false)
    setIntervalHours(null)
  }

  const lastSuccess = status?.last_success ?? null
  const lastAttempt = status?.last_attempt ?? null

  return (
    <div className='mb-6 grid gap-4 rounded-md border p-4'>
      <div className='flex items-center justify-between gap-4'>
        <div className='grid gap-1'>
          <span className='text-sm font-medium'>{t('fx.title')}</span>
          <span className='text-xs text-muted-foreground'>
            {t('fx.description', { provider })}
          </span>
        </div>
        <Switch
          checked={enabled}
          disabled={save.isPending}
          onCheckedChange={(checked) =>
            save.mutate([
              { name: 'fx_auto_sync_enabled', value: checked ? '1' : '0' },
            ])
          }
        />
      </div>

      {enabled && (
        <>
          <div className='grid gap-2 sm:grid-cols-2'>
            <div className='grid gap-1.5'>
              <Label htmlFor='fx-api-key' className='text-xs'>
                {t('fx.api-key')}
              </Label>
              <div className='flex gap-2'>
                <Input
                  id='fx-api-key'
                  type='password'
                  autoComplete='off'
                  placeholder={keyConfigured ? t('fx.key-configured') : t('fx.key-empty')}
                  value={keyInput ?? ''}
                  onChange={(e) => {
                    setKeyInput(e.target.value)
                    setClearKey(false)
                  }}
                />
                {keyConfigured && !clearKey && (
                  <Button
                    variant='ghost'
                    size='icon'
                    title={t('fx.clear-key')}
                    onClick={() => {
                      setKeyInput('')
                      setClearKey(true)
                    }}
                  >
                    <X className='h-4 w-4' />
                  </Button>
                )}
              </div>
              {clearKey && (
                <span className='text-xs text-destructive'>
                  {t('fx.key-will-clear')}
                </span>
              )}
            </div>

            <div className='grid gap-1.5'>
              <Label htmlFor='fx-interval' className='text-xs'>
                {t('fx.interval')}
              </Label>
              <Input
                id='fx-interval'
                type='number'
                min={1}
                max={8760}
                placeholder={String(intervalHours)}
                value={interval ?? ''}
                onChange={(e) => setIntervalHours(e.target.value)}
              />
            </div>
          </div>

          <div className='flex flex-wrap items-center justify-between gap-3'>
            <div className='grid gap-0.5 text-xs text-muted-foreground'>
              {lastSuccess && (
                <span>
                  {t('fx.last-synced', {
                    time: new Date(lastSuccess.at).toLocaleString(),
                  })}
                  {' · '}
                  {t('fx.counts', {
                    updated: String(lastSuccess.updated_count),
                    skipped: String(lastSuccess.skipped_count),
                  })}
                  {lastSuccess.truncated ? ' +' : ''}
                </span>
              )}
              {lastAttempt?.status === 'failed' && lastAttempt.error_code && (
                <span className='text-destructive'>
                  {t('fx.last-failed', { code: lastAttempt.error_code })}
                </span>
              )}
              {!lastAttempt && <span>{t('fx.never-synced')}</span>}
            </div>

            {canSync && (
              <Button
                variant='outline'
                size='sm'
                disabled={syncNow.isPending || clearKey}
                onClick={() => syncNow.mutate()}
              >
                {syncNow.isPending ? (
                  <Loader2 className='h-4 w-4 animate-spin' />
                ) : (
                  <RefreshCw className='h-4 w-4' />
                )}
                {t('fx.sync-now')}
              </Button>
            )}
          </div>

          {(keyInput !== null || clearKey || interval !== null) && (
            <Button
              size='sm'
              className='w-fit'
              disabled={save.isPending}
              onClick={saveSettings}
            >
              {save.isPending && <Loader2 className='animate-spin' />}
              {t('fx.save-changes')}
            </Button>
          )}
        </>
      )}
    </div>
  )
}
