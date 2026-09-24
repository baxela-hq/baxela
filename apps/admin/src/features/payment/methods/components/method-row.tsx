import { ArrowDown, ArrowUp } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Locales } from '../data/routes'
import { methodKeys, type MethodKey, type PaymentMethod } from '../data/schema'

interface MethodRowProps {
  method: PaymentMethod
  isFirst: boolean
  isLast: boolean
  pending: boolean
  onToggle: (method: PaymentMethod, isActive: boolean) => void
  onMove: (method: PaymentMethod, direction: 'up' | 'down') => void
}

export function MethodRow({
  method,
  isFirst,
  isLast,
  pending,
  onToggle,
  onMove,
}: MethodRowProps) {
  const { tLabel, tStatus, tHelpText } = useAppTranslation(
    Locales.PAYMENT_METHOD
  )

  // Future gateways without a translation yet fall back to their API key
  const methodLabel = methodKeys.includes(method.method as MethodKey)
    ? tStatus(`method.${method.method}`)
    : method.method

  const hint = !method.is_registered
    ? tHelpText('not-registered')
    : !method.is_configured
      ? tHelpText('not-configured')
      : tHelpText('active-hint')

  return (
    <div className='flex items-center justify-between gap-4 rounded-lg border p-4 shadow-sm'>
      <div className='flex min-w-0 flex-col gap-1.5'>
        <div className='flex flex-wrap items-center gap-2'>
          <span className='font-medium'>{methodLabel}</span>
          {method.is_active ? (
            <Badge>{tStatus('state.active')}</Badge>
          ) : (
            <Badge variant='secondary'>{tStatus('state.inactive')}</Badge>
          )}
          {!method.is_registered && (
            <Badge variant='destructive'>
              {tStatus('state.not-registered')}
            </Badge>
          )}
          {method.is_registered && !method.is_configured && (
            <Badge variant='outline'>{tStatus('state.not-configured')}</Badge>
          )}
        </div>
        <p className='text-sm text-muted-foreground'>{hint}</p>
      </div>

      <div className='flex shrink-0 items-center gap-1'>
        <Button
          type='button'
          variant='ghost'
          size='icon'
          className='size-8'
          disabled={pending || isFirst}
          onClick={() => onMove(method, 'up')}
        >
          <ArrowUp className='size-4' />
        </Button>
        <Button
          type='button'
          variant='ghost'
          size='icon'
          className='size-8'
          disabled={pending || isLast}
          onClick={() => onMove(method, 'down')}
        >
          <ArrowDown className='size-4' />
        </Button>
        <Switch
          className='ms-2'
          checked={method.is_active}
          disabled={pending || !method.is_registered}
          onCheckedChange={(checked) => onToggle(method, checked)}
          aria-label={tLabel('payment-method')}
        />
      </div>
    </div>
  )
}
