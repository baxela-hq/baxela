import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { RefreshCw, ShieldCheck, ShieldOff } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { Input } from '@/components/ui/input'
import { SkeletonWidget } from '@/components/shared/skeleton-widget.tsx'
import { Locales } from '../data/routes'
import { useTwoFactorMutations, useTwoFactorStatus } from '../hooks/use-two-factor'
import { TwoFactorEnrollment } from './two-factor-enrollment'

/**
 * Self-service 2FA management (S.1 D7): status, enrollment wizard,
 * disable, and recovery-code regeneration. Every mutation requires a
 * valid TOTP or recovery code — a password alone never changes the
 * second factor (D11).
 */
export function TwoFactorSettings() {
  const { t } = useAppTranslation(Locales.TWO_FACTOR)
  const queryClient = useQueryClient()
  const { data: status, isLoading } = useTwoFactorStatus()
  const { disable, regenerate } = useTwoFactorMutations()

  // 'idle' | 'enrolling' — the wizard replaces the card body inline.
  const [mode, setMode] = useState<'idle' | 'enrolling'>('idle')
  const [dialog, setDialog] = useState<'none' | 'disable' | 'regenerate'>('none')
  const [code, setCode] = useState('')
  const [regeneratedCodes, setRegeneratedCodes] = useState<string[] | null>(null)

  if (isLoading) return <SkeletonWidget />

  // Freshly regenerated codes — shown once.
  if (regeneratedCodes) {
    return (
      <div className='grid gap-4'>
        <p className='text-sm text-muted-foreground'>
          {t('management.codes-description')}
        </p>
        <div className='grid grid-cols-2 gap-2 rounded-md border p-4 font-mono text-sm'>
          {regeneratedCodes.map((c) => (
            <span key={c} className='text-center tracking-wider'>
              {c}
            </span>
          ))}
        </div>
        <Button
          className='w-fit'
          type='button'
          onClick={() => setRegeneratedCodes(null)}
        >
          {t('management.codes-done')}
        </Button>
      </div>
    )
  }

  if (mode === 'enrolling') {
    return (
      <TwoFactorEnrollment
        onConfirmed={() => {
          setMode('idle')
          // Refetch shows the enrolled state.
          queryClient.invalidateQueries({ queryKey: ['auth', 'two-factor', 'status'] })
        }}
        onCancel={() => setMode('idle')}
      />
    )
  }

  const enrolled = status?.enrolled ?? false

  const closeDialog = () => {
    setDialog('none')
    setCode('')
  }

  return (
    <div className='grid gap-4'>
      <div className='flex items-center gap-2'>
        {enrolled ? (
          <ShieldCheck className='h-5 w-5 text-primary' />
        ) : (
          <ShieldOff className='h-5 w-5 text-muted-foreground' />
        )}
        <p className='text-sm font-medium'>
          {enrolled
            ? t('management.status-enabled', {
                date: new Date(status?.confirmed_at ?? '').toLocaleDateString(),
              })
            : t('management.status-disabled')}
        </p>
      </div>

      {enrolled ? (
        <>
          <p className='text-sm text-muted-foreground'>
            {t('management.recovery-remaining', {
              count: String(status?.recovery_codes_remaining ?? 0),
            })}
          </p>
          <div className='flex flex-wrap gap-2'>
            <Button
              variant='outline'
              type='button'
              onClick={() => setDialog('regenerate')}
            >
              <RefreshCw />
              {t('management.regenerate')}
            </Button>
            <Button variant='destructive' type='button' onClick={() => setDialog('disable')}>
              <ShieldOff />
              {t('management.disable')}
            </Button>
          </div>
        </>
      ) : (
        <>
          <p className='text-sm text-muted-foreground'>
            {t('management.description')}
          </p>
          <Button className='w-fit' type='button' onClick={() => setMode('enrolling')}>
            <ShieldCheck />
            {t('management.enable')}
          </Button>
        </>
      )}

      {/* Both flows need a code entry inside the dialog (ConfirmDialog children). */}
      <ConfirmDialog
        open={dialog !== 'none'}
        onOpenChange={(open) => {
          if (!open) closeDialog()
        }}
        title={
          dialog === 'disable'
            ? t('management.disable-title')
            : t('management.regenerate-title')
        }
        desc={
          dialog === 'disable'
            ? t('management.disable-description')
            : t('management.regenerate-description')
        }
        destructive={dialog === 'disable'}
        confirmText={dialog === 'disable' ? t('management.disable') : t('management.regenerate')}
        isLoading={disable.isPending || regenerate.isPending}
        disabled={code.trim().length < 6}
        handleConfirm={() => {
          if (dialog === 'disable') {
            disable.mutate(code, { onSuccess: closeDialog })
          } else {
            regenerate.mutate(code, {
              onSuccess: (response) => {
                closeDialog()
                setRegeneratedCodes(response.recovery_codes)
              },
            })
          }
        }}
      >
        <div className='grid gap-2 py-2'>
          <Input
            placeholder={t('management.code-label')}
            autoComplete='one-time-code'
            value={code}
            onChange={(e) => setCode(e.target.value)}
            className='text-center tracking-[0.2em]'
          />
        </div>
      </ConfirmDialog>
    </div>
  )
}
