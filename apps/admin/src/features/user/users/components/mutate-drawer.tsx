import { useEffect } from 'react'
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea.tsx';
import { Locales } from '../data/routes'
import { formSchema, type UserForm, type User, defaultValues } from '../data/schema';
import { useSaveUser } from '../hooks/use-user-mutations'
import { Switch } from '@/components/ui/switch.tsx'




type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: User
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText, tTooltip } = useAppTranslation(Locales.USER)

  const entityName = {
    singular: tLabel("user"),
    plural: tLabel("users")
  };

  const form = useForm<UserForm>({
    resolver: zodResolver(formSchema),
    defaultValues: currentRow ?? defaultValues,
  })

  useEffect(() => {
    form.setValue('mode', isUpdate ? 'update' : 'create')
  })

  const saveUser = useSaveUser()

  const onSubmit = (data: UserForm) => {
    saveUser.mutate(
      { id: currentRow?.id?.toString(), data },
      {
        onSuccess: () => {
          onOpenChange(false)
          form.reset()
        },
      }
    )
  }

  return (
    <Sheet
      open={open}
      onOpenChange={(v) => {
        onOpenChange(v)
        form.reset()
      }}
    >
      <SheetContent className='flex flex-col'>
        <SheetHeader className='text-start'>
          <SheetTitle>
            {
              isUpdate ?
                tPageTitle("form.title_edit", {entity: entityName.singular, id: currentRow?.id?.toString()}) :
                tPageTitle("form.title_create", {entity: entityName.singular})
            }
          </SheetTitle>
          <SheetDescription>
            {tPageTitle("form.subtitle")}
          </SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <form
            id='users-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >

            <FormField
              name="mode"
              control={form.control}
              render={({ field }) => <input type="hidden" {...field} />}
            />

            <FormField
              control={form.control}
              name='email'
              render={({ field }) => (
                <FormItem className='grid gap-2'>
                  <FormLabel htmlFor='email'>{tLabel('email')}</FormLabel>
                  <FormControl>
                    <Input id='email' {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='password'
              render={({ field }) => (
                <FormItem className='grid gap-2'>
                  <FormLabel htmlFor='password'>{tLabel('password')}</FormLabel>
                  <FormControl>
                    <Input id='password'{...field} />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {isUpdate && tHelpText('password')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="is_active"
              render={({ field }) => (
                <FormItem className="flex flex-col gap-3">
                  <div className="space-y-0.5">
                    <FormLabel>{tLabel('is_active')}</FormLabel>
                  </div>
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tTooltip(`is_active.${field.value ? 'active' : 'inactive'}`)}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="is_admin"
              render={({ field }) => (
                <FormItem className="flex flex-col gap-3">
                  <FormLabel>{tLabel('is_admin')}</FormLabel>
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tTooltip(`is_admin.${field.value ? 'active' : 'inactive'}`)}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='comment'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('comment')}</FormLabel>
                  <FormControl>
                    <Textarea {...field} placeholder={tPlaceHolder('textarea')} />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>{tHelpText('comment')}</FormDescription>
                </FormItem>
              )}
            />

          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='users-form' type='submit'>
            {tAction(isUpdate ? 'save' : 'submit')}
          </Button>
          <SheetClose asChild>
            <Button variant='outline'>{tAction('close')}</Button>
          </SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
