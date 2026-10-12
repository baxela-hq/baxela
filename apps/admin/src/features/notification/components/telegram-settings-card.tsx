import { useState } from 'react'
import { ExternalLink, Loader2, Send } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Locales } from '../data/routes'
import { useTelegramNotifications } from '../hooks/use-telegram'

/**
 * Telegram linking card for the notifications settings page: unlinked
 * shows the bot deep link plus a code/chat-id input (the code comes from
 * the bot's /start reply); linked shows the chat with test/unlink
 * actions.
 */
export function TelegramSettingsCard() {
  const { t } = useAppTranslation(Locales.NOTIFICATION)
  const { botInfo, subscription, link, unlink, test } =
    useTelegramNotifications()

  const [value, setValue] = useState('')
  const [error, setError] = useState<string | null>(null)

  const configured = botInfo == null || botInfo.bot_url != null
  const linked = subscription != null

  const submit = (event: React.FormEvent) => {
    event.preventDefault()
    setError(null)

    const trimmed = value.trim()
    if (/^\d{6}$/.test(trimmed)) {
      link.mutate({ code: trimmed, chat_id: null })
    } else if (/^\d+$/.test(trimmed)) {
      link.mutate({ code: null, chat_id: Number(trimmed) })
    } else {
      setError(t('telegram.errors.invalid-input'))
    }
  }

  return (
    <div className='rounded-lg border p-4'>
      <div className='space-y-0.5'>
        <p className='text-base font-medium'>
          <Send size={16} className='me-1.5 inline-block align-[-2px]' />
          {t('telegram.title')}
        </p>
        <p className='text-sm text-muted-foreground'>
          {t('telegram.description')}
        </p>
      </div>

      {!configured && (
        <p className='mt-2 text-sm text-amber-600 dark:text-amber-400'>
          {t('telegram.status.unconfigured')}
        </p>
      )}

      {linked && subscription ? (
        <div className='mt-3 flex flex-wrap items-center justify-between gap-3'>
          <p className='text-sm text-muted-foreground'>
            {subscription.username
              ? t('telegram.linked-as', { username: subscription.username })
              : t('telegram.linked-chat', { chatId: subscription.chat_id })}
          </p>
          <div className='flex items-center gap-2'>
            <Button
              variant='outline'
              size='sm'
              disabled={test.isPending}
              onClick={() => test.mutate()}
            >
              {test.isPending && <Loader2 size={14} className='animate-spin' />}
              {t('telegram.actions.test')}
            </Button>
            <Button
              variant='outline'
              size='sm'
              disabled={unlink.isPending}
              onClick={() => unlink.mutate()}
            >
              {unlink.isPending && (
                <Loader2 size={14} className='animate-spin' />
              )}
              {t('telegram.actions.unlink')}
            </Button>
          </div>
        </div>
      ) : (
        configured && (
          <form className='mt-3 space-y-2' onSubmit={submit}>
            <p className='text-sm text-muted-foreground'>
              {t('telegram.instructions')}{' '}
              {botInfo?.bot_url && (
                <a
                  href={botInfo.bot_url}
                  target='_blank'
                  rel='noreferrer'
                  className='inline-flex items-center gap-1 font-medium text-primary underline-offset-4 hover:underline'
                >
                  {t('telegram.actions.open-bot')}
                  <ExternalLink size={12} />
                </a>
              )}
            </p>
            <div className='flex max-w-md items-center gap-2'>
              <Input
                value={value}
                onChange={(event) => setValue(event.target.value)}
                placeholder={t('telegram.placeholder')}
                aria-label={t('telegram.title')}
                inputMode='numeric'
                className='h-9'
              />
              <Button
                type='submit'
                size='sm'
                className='h-9'
                disabled={link.isPending || value.trim() === ''}
              >
                {link.isPending && <Loader2 size={14} className='animate-spin' />}
                {t('telegram.actions.link')}
              </Button>
            </div>
            {error && <p className='text-sm text-destructive'>{error}</p>}
          </form>
        )
      )}
    </div>
  )
}
