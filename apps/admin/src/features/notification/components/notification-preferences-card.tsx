import { Bell, BellRing, Mail, Send } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Locales } from '../data/routes'
import { type NotificationChannel } from '../data/schema'
import { useNotificationPreferences } from '../hooks/use-notification-preferences'
import { useUpdateNotificationPreference } from '../hooks/use-notification-preference-mutations'

const CHANNEL_ICONS: Record<NotificationChannel, typeof Mail> = {
  email: Mail,
  database: Bell,
  webpush: BellRing,
  telegram: Send,
}

/** i18n key fragment for a notification code: "order.order.created" → "order-order-created" (kebab-case). */
const codeKey = (code: string) => code.replace(/\./g, '-')

/**
 * Per-notification channel toggles for the notifications settings page:
 * one row per admin-configurable notification, one switch per channel it
 * actually uses. Persisted immediately on flip.
 */
export function NotificationPreferencesCard() {
  const { t } = useAppTranslation(Locales.NOTIFICATION)
  const { data, isLoading } = useNotificationPreferences()
  const updatePreference = useUpdateNotificationPreference()

  if (isLoading) {
    return (
      <div className='space-y-4 rounded-lg border p-4'>
        <Skeleton className='h-5 w-48' />
        <Skeleton className='h-5 w-full' />
        <Skeleton className='h-5 w-full' />
      </div>
    )
  }

  const entries = data?.data ?? []
  if (entries.length === 0) return null

  return (
    <div className='rounded-lg border'>
      <div className='border-b p-4'>
        <p className='text-base font-medium'>{t('preferences.title')}</p>
        <p className='text-sm text-muted-foreground'>
          {t('preferences.description')}
        </p>
      </div>
      <div className='divide-y'>
        {entries.map((entry) => (
          <div
            key={entry.code}
            className='flex flex-row items-center justify-between p-4'
          >
            <div className='space-y-0.5 pe-4'>
              <p className='text-sm font-medium'>
                {t(`preferences.codes.${codeKey(entry.code)}.title`)}
              </p>
              <p className='text-sm text-muted-foreground'>
                {t(`preferences.codes.${codeKey(entry.code)}.description`)}
              </p>
            </div>
            <div className='flex shrink-0 items-center gap-4'>
              {entry.channels.map((channel) => {
                const Icon = CHANNEL_ICONS[channel.channel]
                const label = t(`preferences.channels.${channel.channel}`)
                const pending =
                  updatePreference.isPending &&
                  updatePreference.variables?.code === entry.code &&
                  updatePreference.variables?.channel === channel.channel

                return (
                  <label
                    key={channel.channel}
                    className='flex items-center gap-1.5 text-sm text-muted-foreground'
                  >
                    <Icon size={16} />
                    <span className='hidden lg:inline'>{label}</span>
                    <Switch
                      aria-label={`${t(`preferences.codes.${codeKey(entry.code)}.title`)} — ${label}`}
                      checked={channel.enabled}
                      disabled={entry.locked || pending}
                      onCheckedChange={(checked) =>
                        updatePreference.mutate({
                          code: entry.code,
                          channel: channel.channel,
                          is_enabled: checked,
                        })
                      }
                    />
                  </label>
                )
              })}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
