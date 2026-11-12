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

  const otpForm = useForm<{ code: string }>({
    defaultValues: { code: '' },
  })

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

  const onVerify = (data: { code: string }) => {
    verify.mutate({ code: data.code, redirectTo })
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
      <Form {...otpForm}>
        <form
          onSubmit={otpForm.handleSubmit(onVerify)}
          className={cn('grid gap-3', className)}
          {...props}
        >
          <div className='flex items-center gap-2 text-muted-foreground'>
            <ShieldCheck className='h-5 w-5' />
            <p className='text-sm'>
              {t('2fa.enter_code')}
            </p>
          </div>
          <FormField
            control={otpForm.control}
            name='code'
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('2fa.code_label')}</FormLabel>
                <FormControl>
                  <Input
                    placeholder='123456'
                    autoComplete='one-time-code'
                    inputMode='text'
                    autoFocus
                    className='text-center text-lg tracking-[0.3em]'
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <Button className='mt-2' disabled={verify.isPending}>
            {verify.isPending ? <Loader2 className='animate-spin' /> : <ShieldCheck />}
            {t('2fa.verify_button')}
          </Button>
          <Button
            variant='ghost'
            type='button'
            onClick={clearChallenge}
            className='text-muted-foreground'
          >
            {t('2fa.back_to_sign_in')}
          </Button>
        </form>
      </Form>
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
