import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation } from '@tanstack/react-query'
import { Check, Copy, Download, Loader2, ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Locales } from '../data/routes'
import {
  confirmEnrollment,
  confirmTwoFactor,
  enableTwoFactor,
  fetchEnrollmentSecret,
} from '../api/two-factor.api'
import {
  type TwoFactorConfirmResponse,
  type TwoFactorEnrollmentResponse,
  type TwoFactorSecret,
} from '../types/two-factor'

interface TwoFactorEnrollmentProps {
  /**
   * Forced-enrollment mode (super-admin at sign-in): the ENROLLMENT
   * challenge token. When set, the public endpoints are used and
   * onFinish receives the completed sign-in payload.
   */
  challengeToken?: string
  /** Forced mode: called once the user acknowledges the recovery codes. */
  onFinish?: (response: TwoFactorEnrollmentResponse) => void | Promise<unknown>
  /** Self-service mode: called with the recovery codes after confirmation. */
  onConfirmed?: (recoveryCodes: string[]) => void
  /** Self-service mode only: abandon the wizard. */
  onCancel?: () => void
}

/**
 * The shared TOTP enrollment wizard (S.1): QR + manual key → confirm
 * with a live code → one-time recovery codes. Both the forced
 * super-admin flow (challenge token, public endpoints, ends the
 * sign-in) and the self-service settings flow (authed endpoints) run
 * through it.
 */
export function TwoFactorEnrollment({
  challengeToken,
  onFinish,
  onConfirmed,
  onCancel,
}: TwoFactorEnrollmentProps) {
  const { t } = useAppTranslation(Locales.TWO_FACTOR)
  const forced = challengeToken !== undefined

  const [secret, setSecret] = useState<TwoFactorSecret | null>(null)
  const [secretError, setSecretError] = useState(false)
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null)
  // Forced mode: kept for onFinish — the sign-in completes when the
  // codes are acknowledged, not when the confirm call returns.
  const [enrollmentResult, setEnrollmentResult] =
    useState<TwoFactorEnrollmentResponse | null>(null)
  const [copied, setCopied] = useState<'secret' | 'codes' | null>(null)

  // Step 1: fetch the secret + QR on mount. Re-calling replace the
  // unconfirmed secret server-side (D14), so retries are safe.
  useEffect(() => {
    let cancelled = false

    const load = async () => {
      try {
        const result = challengeToken
          ? await fetchEnrollmentSecret(challengeToken)
          : await enableTwoFactor()
        if (!cancelled) setSecret(result)
      } catch {
        if (!cancelled) setSecretError(true)
      }
    }

    load()

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const confirmMutation = useMutation({
    mutationFn: async (
      code: string
    ): Promise<TwoFactorConfirmResponse | TwoFactorEnrollmentResponse> =>
      challengeToken
        ? confirmEnrollment(challengeToken, code)
        : confirmTwoFactor(code),
    onSuccess: (response) => {
      setRecoveryCodes(response.recovery_codes)
      // Forced mode only — the enrollment response carries the token.
      if ('token' in response) {
        setEnrollmentResult(response)
      }
    },
    onError: (err: unknown) => {
      toast.error(
        err instanceof ApiError
          ? err.message
          : t('enrollment.error-generic')
      )
    },
  })

  const codeForm = useForm<{ code: string }>({
    resolver: zodResolver(
      z.object({
        code: z.string().min(6).max(16),
      })
    ),
    defaultValues: { code: '' },
  })

  const copyToClipboard = async (value: string, kind: 'secret' | 'codes') => {
    await navigator.clipboard.writeText(value)
    setCopied(kind)
    setTimeout(() => setCopied(null), 2000)
  }

  const downloadCodes = (codes: string[]) => {
    const blob = new Blob([codes.join('\n')], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'baxela-recovery-codes.txt'
    link.click()
    URL.revokeObjectURL(url)
  }

  // Step 2: the one-time recovery codes.
  if (recoveryCodes) {
    return (
      <div className='grid gap-4'>
        <div className='flex items-center gap-2'>
          <ShieldCheck className='h-5 w-5 text-primary' />
          <h3 className='text-lg font-medium'>{t('enrollment.codes-title')}</h3>
        </div>
        <p className='text-sm text-muted-foreground'>
          {t('enrollment.codes-description')}
        </p>
        <div className='grid grid-cols-2 gap-2 rounded-md border p-4 font-mono text-sm'>
          {recoveryCodes.map((code) => (
            <span key={code} className='text-center tracking-wider'>
              {code}
            </span>
          ))}
        </div>
        <div className='flex flex-wrap gap-2'>
          <Button
            variant='outline'
            type='button'
            onClick={() => copyToClipboard(recoveryCodes.join('\n'), 'codes')}
          >
            {copied === 'codes' ? <Check /> : <Copy />}
            {copied === 'codes'
              ? t('enrollment.copied')
              : t('enrollment.copy-codes')}
          </Button>
          <Button
            variant='outline'
            type='button'
            onClick={() => downloadCodes(recoveryCodes)}
          >
            <Download />
            {t('enrollment.download-codes')}
          </Button>
          <Button
            type='button'
            onClick={() => {
              if (forced && enrollmentResult) {
                onFinish?.(enrollmentResult)
              } else {
                onConfirmed?.(recoveryCodes)
              }
            }}
          >
            {t('enrollment.done')}
          </Button>
        </div>
      </div>
    )
  }

  // Step 1: scan + confirm.
  return (
    <div className='grid gap-4'>
      <div className='flex items-center gap-2'>
        <ShieldCheck className='h-5 w-5 text-primary' />
        <h3 className='text-lg font-medium'>{t('enrollment.title')}</h3>
      </div>
      <p className='text-sm text-muted-foreground'>
        {forced
          ? t('enrollment.forced-description')
          : t('enrollment.description')}
      </p>

      {secretError ? (
        <div className='grid gap-2'>
          <p className='text-sm text-destructive'>
            {t('enrollment.error-secret')}
          </p>
          <Button variant='outline' type='button' onClick={() => onCancel?.()}>
            {t('enrollment.back')}
          </Button>
        </div>
      ) : !secret ? (
        <div className='flex items-center justify-center gap-2 rounded-md border p-8 text-sm text-muted-foreground'>
          <Loader2 className='h-4 w-4 animate-spin' />
          {t('enrollment.loading')}
        </div>
      ) : (
        <>
          <div className='flex flex-col items-center gap-2'>
            {/* data:image/svg+xml inline — no CDN dependency */}
            <img
              src={secret.qr_code_uri}
              alt={t('enrollment.scan')}
              className='h-48 w-48 rounded-md border bg-white p-2'
            />
            <button
              type='button'
              className='inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-4 hover:underline'
              onClick={() => copyToClipboard(secret.secret, 'secret')}
            >
              {copied === 'secret' ? <Check className='h-3 w-3' /> : <Copy className='h-3 w-3' />}
              {t('enrollment.or-enter-key')}{' '}
              <span className='font-mono'>{secret.secret}</span>
            </button>
          </div>

          <Form {...codeForm}>
            <form
              onSubmit={codeForm.handleSubmit(({ code }) =>
                confirmMutation.mutate(code)
              )}
              className='grid gap-3'
            >
              <FormField
                control={codeForm.control}
                name='code'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('enrollment.code-label')}</FormLabel>
                    <FormControl>
                      <Input
                        placeholder='123456'
                        autoComplete='one-time-code'
                        inputMode='numeric'
                        autoFocus
                        className='text-center text-lg tracking-[0.3em]'
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className='flex flex-wrap gap-2'>
                <Button type='submit' disabled={confirmMutation.isPending}>
                  {confirmMutation.isPending ? (
                    <Loader2 className='animate-spin' />
                  ) : (
                    <ShieldCheck />
                  )}
                  {forced
                    ? t('enrollment.confirm-sign-in')
                    : t('enrollment.confirm')}
                </Button>
                {onCancel && !forced && (
                  <Button variant='ghost' type='button' onClick={onCancel}>
                    {t('enrollment.cancel')}
                  </Button>
                )}
              </div>
            </form>
          </Form>
        </>
      )}
    </div>
  )
}
