import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea.tsx';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Locales } from '../data/routes'
import {
  formSchema,
  type MethodForm,
  type Method,
  buildDefaultValues,
  buildEditValues,
} from '../data/schema'
import { useLanguages } from '@/features/core/languages/hooks/use-languages'
import { useSaveMethod } from '../hooks/use-method-mutations'


type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: Method
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText } = useAppTranslation(Locales.METHOD)

  const entityName = {
    singular: tLabel("method"),
    plural: tLabel("methods")
  };

  const { data: languages, isLoading: languagesIsLoading } = useLanguages()

  const languagesSafe = languages ?? []

  const form = useForm<MethodForm>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  })

  useEffect(() => {
    if (languagesSafe.length > 0) {
      form.reset(buildEditValues(languagesSafe, currentRow))
    }
  }, [languages, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const saveMethod = useSaveMethod()

  const onSubmit = (data: MethodForm) => {
    saveMethod.mutate(
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
            id='methods-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormField
              control={form.control}
              name='code'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('code')}</FormLabel>
                  <FormControl>
                    <Input {...field} placeholder={tPlaceHolder('input')} />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('code')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='is_active'
              render={({ field }) => (
                <FormItem className='flex flex-row items-center justify-between rounded-lg border p-3'>
                  <div className='space-y-0.5'>
                    <FormLabel>{tLabel('is_active')}</FormLabel>
                  </div>
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  </FormControl>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='position'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('position')}</FormLabel>
                  <FormControl>
                    <Input type="number" max="255" {...field} placeholder={tPlaceHolder('input')} />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('position')}
                  </FormDescription>
                </FormItem>
              )}
            />

            {languagesIsLoading && (
              <div className='py-8 text-center text-sm text-muted-foreground'>
                Loading languages...
              </div>
            )}

            {!languagesIsLoading && languagesSafe.length > 0 && (
              <Tabs defaultValue={languagesSafe[0]?.code} className='w-full'>
                <TabsList className='w-full'>
                  {languagesSafe.map((language) => (
                    <TabsTrigger key={language.code} value={language.code}>
                      {language.code.toUpperCase()}
                    </TabsTrigger>
                  ))}
                </TabsList>

                {languagesSafe.map((language, index) => (
                  <TabsContent key={language.code} value={language.code} className='space-y-4'>
                    <FormField
                      control={form.control}
                      name={`translations.${index}.name`}
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{tLabel('name')}</FormLabel>
                          <FormControl>
                            <Input {...field} placeholder={tPlaceHolder('input')} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name={`translations.${index}.description`}
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{tLabel('description')}</FormLabel>
                          <FormControl>
                            <Textarea {...field} value={field.value ?? ''} placeholder={tPlaceHolder('textarea')} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </TabsContent>
                ))}
              </Tabs>
            )}
          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='methods-form' type='submit'>
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
