import { Loader2, MonitorSmartphone } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Locales } from '../data/routes'
import { useWebPush } from '../hooks/use-web-push'

/**
 * Browser web push toggle for the notifications settings page: one row
 * describing OS-level notifications, with state-aware hints (enabled /
 * blocked / unsupported) instead of a bare switch.
 */
export function PushSettingsCard() {
  const { t } = useAppTranslation(Locales.NOTIFICATION)
  const { status, enabled, enable, disable } = useWebPush()

  const busy = status === 'enabling' || status === 'disabling'
  const interactive = status === 'supported'

  return (
    <div className='flex flex-row items-center justify-between rounded-lg border p-4'>
      <div className='space-y-0.5 pe-4'>
        <p className='text-base font-medium'>
          <MonitorSmartphone
            size={16}
            className='me-1.5 inline-block align-[-2px]'
          />
          {t('push.title')}
        </p>
        <p className='text-sm text-muted-foreground'>
          {t('push.description')}
        </p>
        {status !== 'supported' && status !== 'enabling' && (
          <p className='text-sm text-amber-600 dark:text-amber-400'>
            {t(`push.status.${status}`)}
          </p>
        )}
      </div>
      {busy ? (
        <Button variant='outline' size='sm' disabled>
          <Loader2 size={14} className='animate-spin' />
          {status === 'enabling'
            ? t('push.actions.enabling')
            : t('push.actions.disabling')}
        </Button>
      ) : (
        <Switch
          aria-label={t('push.title')}
          checked={enabled}
          disabled={!interactive}
          onCheckedChange={(checked) => {
            if (checked) void enable()
            else void disable()
          }}
        />
      )}
    </div>
  )
}
