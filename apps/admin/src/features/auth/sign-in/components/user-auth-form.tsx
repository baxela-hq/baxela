import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link } from '@tanstack/react-router'
import { Loader2, LogIn } from 'lucide-react'
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

interface UserAuthFormProps extends React.HTMLAttributes<HTMLFormElement> {
  redirectTo?: string
}

export function UserAuthForm({
  className,
  redirectTo,
  ...props
}: UserAuthFormProps) {
  const signIn = useSignIn()
  const { t, tLabel, tPlaceHolder, tAction } = useAppTranslation(
    Locales.SIGN_IN
  )

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
              <Link
                to='/forgot-password'
                className='absolute end-0 -top-0.5 text-sm font-medium text-muted-foreground hover:opacity-75'
              >
                {tAction('forgot-password')}
              </Link>
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
