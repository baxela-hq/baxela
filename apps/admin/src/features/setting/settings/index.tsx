import { useEffect, useState } from 'react';
import { type z } from 'zod';
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
import { formSchema, defaultValues, type SettingRequest, type Setting } from './data/schema';





























const route = getRouteApi('/_authenticated/setting/settings/')

export function Settings() {
  const [isUpdating, setIsUpdating] = useState(false)
  const search = route.useSearch()
  const queryClient = useQueryClient()
  // const [isLoading, setIsLoading] = useState(false)
  const [values, setValues] = useState(defaultValues)
  const { tAction,tPageTitle, tPlaceHolder, tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tStatus, tMessage: tM, tTooltip } = useAppTranslation(Locales.SETTING)

  const entityName = {
    singular: tLabel("setting"),
    plural: tLabel("settings")
  };

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues,
    values,
  });

  const { data, isSuccess, isLoading } = useQuery<Setting[]>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchSettings(search),
  });

// Side effect runs *after* data successfully loaded
  useEffect(() => {
    if (isSuccess && data) {
      const newValues = {
        website_title: data.find(item => item.name === 'website_title')?.value || "",
        website_keywords: data.find(item => item.name === 'website_keywords')?.value || "",
        website_description: data.find(item => item.name === 'website_description')?.value || "",
      };
      setValues(newValues);
    }
  }, [isSuccess, data]);


  async function handleSubmit(values: z.infer<typeof formSchema>) {
    setIsUpdating(true)
    try {
      const body: SettingRequest[] = [
        { name: 'website_title', value: values.website_title },
        { name: 'website_keywords', value: values.website_keywords },
        { name: 'website_description', value: values.website_description },
      ];
      await updateSettings(body)
      await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] });
      toast.error(tM('success.updated'));
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


  if (isLoading) return <SkeletonWidget />; // or any loading UI

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
            className={isLoading ? 'hidden' : 'space-y-8'}
          >
            <div className='grid gap-4'>

              {/* website_title Field */}
              <FormField
                control={form.control}
                name='website_title'
                render={({ field }) => (
                  <FormItem className='grid gap-2'>
                    <FormLabel htmlFor='website_title'>{tStatus('name.website_title')}</FormLabel>
                    <FormControl>
                      <Input
                        id='website_title'
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

              {/* website_description Field */}
              <FormField
                control={form.control}
                name='website_description'
                render={({ field }) => (
                  <FormItem className='grid gap-2'>
                    <FormLabel htmlFor='website_description'>{tStatus('name.website_description')}</FormLabel>
                    <FormControl>
                      <Textarea
                        id='website_description'
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

              {/* website_keywords Field */}
              <FormField
                control={form.control}
                name='website_keywords'
                render={({ field }) => (
                  <FormItem className='grid gap-2'>
                    <FormLabel htmlFor='website_keywords'>{tStatus('name.website_keywords')}</FormLabel>
                    <FormControl>
                      <Textarea
                        id='website_keywords'
                        placeholder={tPlaceHolder('textarea')}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                    <FormDescription>
                      {tTooltip('name.website_keywords')}
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
