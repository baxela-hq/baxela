import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getRouteApi } from '@tanstack/react-router';
import { ApiError } from '@/shared/lib/api-error.ts';
import { parseAndToastError } from '@/shared/lib/utils';
import { LoaderIcon, SaveIcon } from 'lucide-react';
import { toast } from 'sonner';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea.tsx';
import { ConfigDrawer } from '@/components/config-drawer';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { ProfileDropdown } from '@/components/profile-dropdown';
import { Search } from '@/components/search';
import { SkeletonWidget } from '@/components/shared/skeleton-widget.tsx';
import { ThemeSwitch } from '@/components/theme-switch';
import { fetchSettings, updateSettings } from './api/settings.api.ts';
import { FeatureRoutes, Locales } from './data/routes';
import {
  formSchema,
  defaultValues,
  buildSettingsValues,
  buildSettingsRequest,
  type SettingsForm,
  type Setting,
} from './data/schema';
import { fetchLanguages } from '@/features/core/languages/api/languages.api'
import { fetchCurrencies } from '@/features/core/currencies/api/currencies.api'
import type { Language, Currency } from '@/shared/types/locale.types'

const route = getRouteApi('/_authenticated/setting/settings/')

export function Settings() {
  const [isUpdating, setIsUpdating] = useState(false)
  const search = route.useSearch()
  const queryClient = useQueryClient()
  const { tAction, tPageTitle, tPlaceHolder, tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tStatus, tTooltip } = useAppTranslation(Locales.SETTING)

  const entityName = {
    singular: tLabel("setting"),
    plural: tLabel("settings")
  };

  const { data: settings, isLoading: settingsLoading } = useQuery<Setting[]>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchSettings(search),
  });

  const { data: languages, isLoading: languagesLoading } = useQuery<Language[]>({
    queryKey: ['languages'],
    queryFn: () => fetchLanguages(),
  });

  const { data: currencies, isLoading: currenciesLoading } = useQuery<Currency[]>({
    queryKey: ['currencies'],
    queryFn: () => fetchCurrencies(),
  });

  const languagesSafe = languages ?? []
  const currenciesSafe = currencies ?? []
  const settingsSafe = settings ?? []
  const isLoading = settingsLoading || languagesLoading || currenciesLoading

  const form = useForm<SettingsForm>({
    resolver: zodResolver(formSchema),
    defaultValues,
  });

  useEffect(() => {
    if (!isLoading) {
      form.reset(buildSettingsValues(languagesSafe, settingsSafe));
    }
  }, [isLoading]) // eslint-disable-line react-hooks/exhaustive-deps

  async function handleSubmit(values: SettingsForm) {
    setIsUpdating(true)
    try {
      await updateSettings(buildSettingsRequest(values, languagesSafe))
      await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] });
      toast.success(tMessage('success.record.updated', { name: entityName.singular }));
    } catch (error: unknown) {
      if (error instanceof ApiError) {
        parseAndToastError(error)
      } else {
        toast.error(tMessage('error.general'));
      }
    } finally {
      setIsUpdating(false)
    }
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
            <div className='grid gap-4'>

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

            </div>
            <div className='flex-col'>
              <Button type='submit' className='btn' disabled={isUpdating}>
                <LoaderIcon className={isUpdating ? 'animate-spin' : 'hidden'} />
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