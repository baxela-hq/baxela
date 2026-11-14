import { useState } from 'react'
import { Copy, KeyRound } from 'lucide-react'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Locales } from '../data/routes'

/**
 * The once-only signing secret (gap-C2 D8): shown exactly once after
 * create/regenerate, with copy support. It is never fetched again by
 * any API — close it and it is gone.
 */
export function SecretDialog({
  secret,
  onClose,
}: {
  secret: string | null
  onClose: () => void
}) {
  const { tLabel, tHelpText, tAction } = useAppTranslation(Locales.WEBHOOK)
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    await navigator.clipboard.writeText(secret ?? '')
    setCopied(true)
    toast.success(tHelpText('secret_copied'))
  }

  return (
    <Dialog open={secret !== null} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className='sm:max-w-lg'>
        <DialogHeader>
          <DialogTitle className='flex items-center gap-2'>
            <KeyRound className='size-4' />
            {tLabel('secret-title')}
          </DialogTitle>
          <DialogDescription>{tHelpText('secret_once')}</DialogDescription>
        </DialogHeader>

        <div className='flex items-center gap-2'>
          <code className='flex-1 truncate rounded-md border bg-muted px-3 py-2 font-mono text-sm'>
            {secret}
          </code>
          <Button size='icon' variant='outline' onClick={copy}>
            <Copy className='size-4' />
            <span className='sr-only'>{tAction('copy')}</span>
          </Button>
        </div>

        <p className='text-muted-foreground text-xs'>
          {tHelpText('secret_verify')}
        </p>

        <Button onClick={onClose}>{tAction('done')}</Button>
        {copied && null}
      </DialogContent>
    </Dialog>
  )
}
