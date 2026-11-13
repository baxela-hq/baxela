import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Loader2, LogIn, ShieldCheck } from 'lucide-react'
import { IconFacebook, IconGithub } from '@/assets/brand-icons'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
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
import { PasswordInput } from '@/components/password-input'
import { Locales } from '../data/routes'
import {
  buildSignInFormSchema,
  type SignInForm,
} from '../data/schema'
import { useSignIn } from '../hooks/use-sign-in'
import { TwoFactorEnrollment } from '@/features/auth/two-factor/components/two-factor-enrollment'

interface UserAuthFormProps extends React.HTMLAttributes<HTMLFormElement> {
  redirectTo?: string
}

export function UserAuthForm({
  className,
  redirectTo,
  ...props
}: UserAuthFormProps) {
  const { signIn, verify, completeSignIn, challengeToken, enrollmentToken, clearChallenge } =
    useSignIn()
  const { t, tLabel, tPlaceHolder, tAction } = useAppTranslation(
    Locales.SIGN_IN
  )

  // The 2FA challenge code is plain useState on purpose: the RHF
  // Controller in this component's challenge branch silently discarded
  // every keystroke (the typed value reached the DOM but the form state
  // committed '' — see the M.5 diagnosis). One field, no validation —
  // useState is the whole requirement.
  const [otpCode, setOtpCode] = useState('')

  const form = useForm<SignInForm>({
    resolver: zodResolver(
      buildSignInFormSchema({
        emailRequired: t('form.validation.email-required'),
        passwordRequired: t('form.validation.password-required'),
        passwordMinLength: t('form.validation.password-min-length'),
      })
    ),
    defaultValues: {
      email: '',
      password: '',
    },
  })

  const onSubmit = (data: SignInForm) => {
    signIn.mutate({ ...data, redirectTo })
  }

  const onVerify = () => {
    verify.mutate({ code: otpCode, redirectTo })
  }

  // S.1 D9: super-admin forced enrollment — the wizard confirms the
  // TOTP code and issues the sign-in token; completion continues the
  // normal post-sign-in chain only after the codes are acknowledged.
  if (enrollmentToken) {
    return (
      <TwoFactorEnrollment
        challengeToken={enrollmentToken}
        onFinish={async (response) => {
          clearChallenge()
          await completeSignIn(response, redirectTo)
        }}
        onCancel={clearChallenge}
      />
    )
  }

  // S.1 D3: 2FA challenge — show the OTP input instead of the password form.
  if (challengeToken) {
    return (
      <form
        onSubmit={(e) => {
          e.preventDefault()
          onVerify()
        }}
        className={cn('grid gap-3', className)}
        {...props}
      >
        <div className='flex items-center gap-2 text-muted-foreground'>
          <ShieldCheck className='h-5 w-5' />
          <p className='text-sm'>
            {t('2fa.enter_code')}
          </p>
        </div>
        <label className='grid gap-2'>
          <span className='text-sm font-medium'>{t('2fa.code_label')}</span>
          <input
            type='text'
            placeholder='123456'
            autoComplete='one-time-code'
            inputMode='text'
            autoFocus
            className='flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground md:text-sm dark:bg-input/30 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 text-center text-lg tracking-[0.3em]'
            value={otpCode}
            onChange={(e) => setOtpCode(e.target.value)}
          />
        </label>
        <button
          className='mt-2 inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition-[color,box-shadow] hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50'
          disabled={verify.isPending}
        >
          {verify.isPending ? (
            <Loader2 className='animate-spin' />
          ) : (
            <ShieldCheck />
          )}
          {t('2fa.verify_button')}
        </button>
        <button
          type='button'
          onClick={clearChallenge}
          className='text-muted-foreground text-sm underline-offset-4 hover:underline'
        >
          {t('2fa.back_to_sign_in')}
        </button>
      </form>
    )
  }

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className={cn('grid gap-3', className)}
        {...props}
      >
        <FormField
          control={form.control}
          name='email'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{tLabel('email')}</FormLabel>
              <FormControl>
                <Input placeholder={tPlaceHolder('email')} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='password'
          render={({ field }) => (
            <FormItem className='relative'>
              <FormLabel>{tLabel('password')}</FormLabel>
              <FormControl>
                <PasswordInput placeholder={tPlaceHolder('password')} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button className='mt-2' disabled={signIn.isPending}>
          {signIn.isPending ? <Loader2 className='animate-spin' /> : <LogIn />}
          {tAction('sign-in')}
        </Button>

        <div className='relative my-2'>
          <div className='absolute inset-0 flex items-center'>
            <span className='w-full border-t' />
          </div>
          <div className='relative flex justify-center text-xs uppercase'>
            <span className='bg-background px-2 text-muted-foreground'>
              {t('card.or-continue-with')}
            </span>
          </div>
        </div>

        <div className='grid grid-cols-2 gap-2'>
          <Button variant='outline' type='button' disabled={signIn.isPending}>
            <IconGithub className='h-4 w-4' /> GitHub
          </Button>
          <Button variant='outline' type='button' disabled={signIn.isPending}>
            <IconFacebook className='h-4 w-4' /> Facebook
          </Button>
        </div>
      </form>
    </Form>
  )
}
