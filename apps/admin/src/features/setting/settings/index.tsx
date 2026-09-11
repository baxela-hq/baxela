import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { getRouteApi } from '@tanstack/react-router';
import { LoaderIcon, SaveIcon } from 'lucide-react';
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea.tsx';
import { TiptapEditor } from '@/components/tiptap/tiptap-editor';
import { ConfigDrawer } from '@/components/config-drawer';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { ProfileDropdown } from '@/components/profile-dropdown';
import { Search } from '@/components/search';
import { SkeletonWidget } from '@/components/shared/skeleton-widget.tsx';
import { ThemeSwitch } from '@/components/theme-switch';
import { useSettings } from './hooks/use-settings';
import { useUpdateSettings } from './hooks/use-setting-mutations';
import { useLanguages } from '@/features/core/languages/hooks/use-languages'
import { useCurrencies } from '@/features/core/currencies/hooks/use-currencies'
import { Locales } from './data/routes';
import {
  formSchema,
  defaultValues,
  buildSettingsValues,
  type SettingsForm,
} from './data/schema';

const route = getRouteApi('/_authenticated/setting/settings/')

export function Settings() {
  const search = route.useSearch()
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tStatus, tTooltip, tHelpText } = useAppTranslation(Locales.SETTING)

  const entityName = {
    singular: tLabel("setting"),
    plural: tLabel("settings")
  };

  const { data: settings, isLoading: settingsLoading } = useSettings(search);

  const { data: languages, isLoading: languagesLoading } = useLanguages();

  const { data: currencies, isLoading: currenciesLoading } = useCurrencies();

  const updateSettings = useUpdateSettings()

  const languagesSafe = languages ?? []
  const currenciesSafe = currencies ?? []
  const settingsSafe = settings ?? []
  const isLoading = settingsLoading || languagesLoading || currenciesLoading

  const hasAnnouncementGroup = settingsSafe.some((s) => s.group === 'announcement')

  const form = useForm<SettingsForm>({
    resolver: zodResolver(formSchema),
    defaultValues,
  });

  useEffect(() => {
    if (!isLoading) {
      form.reset(buildSettingsValues(languagesSafe, settingsSafe));
    }
  }, [isLoading]) // eslint-disable-line react-hooks/exhaustive-deps

  const handleSubmit = (values: SettingsForm) => {
    updateSettings.mutate({ values, languages: languagesSafe })
  }

  if (isLoading) return <SkeletonWidget />;

  return (
    <>
      <Header fixed>
        <Search />
        <div className='ms-auto flex items-center space-x-4'>
          <ThemeSwitch />
          <ConfigDrawer />
          <ProfileDropdown />
        </div>
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              { tPageTitle("index.title", {entity: entityName.plural}) }
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("index.subtitle", {entity: entityName.plural})}
            </p>
          </div>
        </div>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(handleSubmit)}
            className='space-y-8'
          >
            <Tabs defaultValue='general' className='w-full'>
              <TabsList className='w-full'>
                <TabsTrigger value='general'>{tLabel('general')}</TabsTrigger>
                {hasAnnouncementGroup && (
                  <TabsTrigger value='announcement'>{tLabel('announcement')}</TabsTrigger>
                )}
              </TabsList>

              <TabsContent value='general' className='grid gap-4'>
                {languagesSafe.length > 0 && (
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
                          name={`website_title.translations.${index}.value`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2'>
                              <FormLabel htmlFor={`website_title-${language.code}`}>{tStatus('name.website_title')}</FormLabel>
                              <FormControl>
                                <Input
                                  id={`website_title-${language.code}`}
                                  placeholder={tPlaceHolder('input')}
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                              <FormDescription>
                                {tTooltip('name.website_title')}
                              </FormDescription>
                            </FormItem>
                          )}
                        />

                        <FormField
                          control={form.control}
                          name={`website_description.translations.${index}.value`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2'>
                              <FormLabel htmlFor={`website_description-${language.code}`}>{tStatus('name.website_description')}</FormLabel>
                              <FormControl>
                                <Textarea
                                  id={`website_description-${language.code}`}
                                  placeholder={tPlaceHolder('textarea')}
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                              <FormDescription>
                                {tTooltip('name.website_description')}
                              </FormDescription>
                            </FormItem>
                          )}
                        />
                      </TabsContent>
                    ))}
                  </Tabs>
                )}

                <FormField
                  control={form.control}
                  name='language_id'
                  render={({ field }) => (
                    <FormItem className='grid gap-2'>
                      <FormLabel htmlFor='language_id'>{tStatus('name.language_id')}</FormLabel>
                      <Select
                        onValueChange={field.onChange}
                        value={field.value || undefined}
                      >
                        <FormControl>
                          <SelectTrigger id='language_id' className='w-full'>
                            <SelectValue placeholder={tPlaceHolder('select')} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {languagesSafe.map((language) => (
                            <SelectItem key={language.id} value={String(language.id)}>
                              {language.native_name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                      <FormDescription>
                        {tTooltip('name.language_id')}
                      </FormDescription>
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name='currency_id'
                  render={({ field }) => (
                    <FormItem className='grid gap-2'>
                      <FormLabel htmlFor='currency_id'>{tStatus('name.currency_id')}</FormLabel>
                      <Select
                        onValueChange={field.onChange}
                        value={field.value || undefined}
                      >
                        <FormControl>
                          <SelectTrigger id='currency_id' className='w-full'>
                            <SelectValue placeholder={tPlaceHolder('select')} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {currenciesSafe.map((currency) => (
                            <SelectItem key={currency.id} value={String(currency.id)}>
                              {currency.native_name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                      <FormDescription>
                        {tTooltip('name.currency_id')}
                      </FormDescription>
                    </FormItem>
                  )}
                />
              </TabsContent>

              {hasAnnouncementGroup && (
                <TabsContent value='announcement' className='grid gap-4'>
                  <FormField
                    control={form.control}
                    name='announcement_bar_enabled'
                    render={({ field }) => (
                      <FormItem className='grid gap-2'>
                        <FormLabel htmlFor='announcement_bar_enabled'>{tStatus('name.announcement_bar_enabled')}</FormLabel>
                        <FormControl>
                          <Switch
                            id='announcement_bar_enabled'
                            checked={field.value}
                            onCheckedChange={field.onChange}
                          />
                        </FormControl>
                        <FormMessage />
                        <FormDescription>
                          {tTooltip('name.announcement_bar_enabled')}
                        </FormDescription>
                      </FormItem>
                    )}
                  />

                  {languagesSafe.length > 0 && (
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
                            name={`announcement_text.translations.${index}.value`}
                            render={({ field }) => (
                              <FormItem className='grid gap-2'>
                                <FormLabel htmlFor={`announcement_text-${language.code}`}>{tStatus('name.announcement_text')}</FormLabel>
                                <FormControl>
                                  <TiptapEditor
                                    initialContent={field.value}
                                    onUpdate={({ html }) => field.onChange(html)}
                                  />
                                </FormControl>
                                <FormMessage />
                                <FormDescription>
                                  {tHelpText('announcement_text')}
                                </FormDescription>
                              </FormItem>
                            )}
                          />
                        </TabsContent>
                      ))}
                    </Tabs>
                  )}
                </TabsContent>
              )}
            </Tabs>

            <div className='flex-col'>
              <Button type='submit' className='btn' disabled={updateSettings.isPending}>
                <LoaderIcon className={updateSettings.isPending ? 'animate-spin' : 'hidden'} />
                <SaveIcon />
                {tAction('submit')}
              </Button>
            </div>
          </form>
        </Form>
      </Main>

    </>
  )
}