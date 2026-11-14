import { useState } from 'react'
import { Loader2 } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EVENT_TYPES, type WebhookEndpoint } from '../data/schema'
import { Locales } from '../data/routes'

type EndpointInput = {
  name: string
  url: string
  events: string[]
  is_active: boolean
}

/**
 * Create/edit endpoint dialog. Fixed payload shape (the backend request
 * accepts exactly name/url/events/is_active); events come from the v1
 * EventCatalog. The form remounts per dialog open (keyed by endpoint),
 * so state initializes from props — no effect-driven resets.
 */
export function EndpointDialog({
  open,
  endpoint,
  submitting,
  onClose,
  onSubmit,
}: {
  open: boolean
  endpoint: WebhookEndpoint | null
  submitting: boolean
  onClose: () => void
  onSubmit: (input: EndpointInput) => void
}) {
  const { tLabel, tHelpText } = useAppTranslation(Locales.WEBHOOK)

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className='sm:max-w-lg'>
        <DialogHeader>
          <DialogTitle>
            {endpoint === null ? tLabel('create-title') : tLabel('edit-title')}
          </DialogTitle>
          <DialogDescription>{tHelpText('endpoint_help')}</DialogDescription>
        </DialogHeader>

        {open && (
          <EndpointForm
            key={endpoint?.id ?? 'new'}
            endpoint={endpoint}
            submitting={submitting}
            onCancel={onClose}
            onSubmit={onSubmit}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}

function EndpointForm({
  endpoint,
  submitting,
  onCancel,
  onSubmit,
}: {
  endpoint: WebhookEndpoint | null
  submitting: boolean
  onCancel: () => void
  onSubmit: (input: EndpointInput) => void
}) {
  const { tLabel, tPlaceHolder, tHelpText, tAction } = useAppTranslation(
    Locales.WEBHOOK
  )

  const [name, setName] = useState(endpoint?.name ?? '')
  const [url, setUrl] = useState(endpoint?.url ?? '')
  const [events, setEvents] = useState<string[]>(endpoint?.events ?? [])
  const [isActive, setIsActive] = useState(endpoint?.is_active ?? true)

  const valid = name.trim() !== '' && url.trim() !== '' && events.length > 0

  const toggleEvent = (type: string, checked: boolean) => {
    setEvents((prev) =>
      checked ? [...prev, type] : prev.filter((t) => t !== type)
    )
  }

  return (
    <>
      <div className='grid gap-4'>
        <div className='grid gap-2'>
          <Label htmlFor='webhook-name'>{tLabel('name')}</Label>
          <Input
            id='webhook-name'
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder={tPlaceHolder('name')}
          />
        </div>

        <div className='grid gap-2'>
          <Label htmlFor='webhook-url'>{tLabel('url')}</Label>
          <Input
            id='webhook-url'
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            placeholder={tPlaceHolder('url')}
          />
          <p className='text-muted-foreground text-xs'>
            {tHelpText('url_help')}
          </p>
        </div>

        <div className='grid gap-2'>
          <Label>{tLabel('events')}</Label>
          <div className='grid grid-cols-2 gap-2'>
            {EVENT_TYPES.map((type) => (
              <label
                key={type}
                className='flex items-center gap-2 text-sm font-normal'
              >
                <Checkbox
                  checked={events.includes(type)}
                  onCheckedChange={(checked) =>
                    toggleEvent(type, checked === true)
                  }
                />
                <span className='font-mono text-xs'>{type}</span>
              </label>
            ))}
          </div>
        </div>

        <label className='flex items-center gap-2 text-sm font-normal'>
          <Checkbox
            checked={isActive}
            onCheckedChange={(checked) => setIsActive(checked === true)}
          />
          {tLabel('is_active')}
        </label>
      </div>

      <DialogFooter>
        <Button variant='outline' onClick={onCancel}>
          {tAction('cancel')}
        </Button>
        <Button
          disabled={!valid || submitting}
          onClick={() =>
            onSubmit({
              name: name.trim(),
              url: url.trim(),
              events,
              is_active: isActive,
            })
          }
        >
          {submitting && <Loader2 className='animate-spin' />}
          {tAction('save')}
        </Button>
      </DialogFooter>
    </>
  )
}
