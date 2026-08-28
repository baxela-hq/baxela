import { useEffect, useState } from 'react';
import { type z } from 'zod';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { fetchLanguages } from '@/features/core/languages/api/languages.api'
import type { Language } from '@/shared/types/locale.types'
import { parseAndToastError } from '@/shared/lib/utils';
import { ApiError } from '@/shared/lib/api-error';
import { ListCheckIcon, LoaderIcon, SaveIcon, ArrowLeftIcon } from 'lucide-react';
import { toast } from 'sonner';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage, FormDescription } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea.tsx';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ConfigDrawer } from '@/components/config-drawer';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { ProfileDropdown } from '@/components/profile-dropdown';
import { Search } from '@/components/search'
import ImageUploader from '@/components/shared/image-uploader.tsx'
import { ThemeSwitch } from '@/components/theme-switch'
import { TiptapEditor } from '@/components/tiptap/tiptap-editor'
import { FeatureRoutes, Locales } from './data/routes';
import { BASE_URL, createPage, fetchOnePage, updatePage } from './api/pages.api.ts';
import { Provider } from './components/provider.tsx';
import { formSchema, statuses, buildDefaultValues, buildEditValues, type PageForm, type Page } from './data/schema';


export function PageForm() {
  const [isLoading, setIsLoading] = useState(false)
  const [id, setId] = useState<number | null>(null)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [currentRow, setCurrentRow] = useState<Page | null>(null)
  const [activeTab, setActiveTab] = useState('general')
  const { tAction,tPageTitle, tPlaceHolder, tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tStatus } = useAppTranslation(Locales.PAGE)

  const entityName = {
    singular: tLabel("page"),
    plural: tLabel("pages")
  };

  const { data: languages, isLoading: languagesIsLoading } = useQuery<Language[]>({
    queryKey: ['languages'],
    queryFn: () => fetchLanguages(),
  });

  const languagesSafe = languages ?? []

  const uploadEndpoints = {
    a: `${BASE_URL}/${id}/images`,
    b: `${BASE_URL}/${id}/images/$id`,
  }

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  });

  useEffect(() => {
    if (languagesSafe.length > 0) {
      form.reset(buildEditValues(languagesSafe, currentRow ?? undefined))
    }
  }, [languages, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    const currentPath = window.location.pathname
    const match = currentPath.match(/^\/content\/pages\/(\d+)\/edit$/);

    if (match && match[1]) {
      const intId = Number(match[1])
      setId(intId)
      const fetchData = async () => {
        getItem(intId)
      }
      fetchData()
    }
  }, [])

  async function getItem(id: number) {
    setIsLoading(true)
    const result = await fetchOnePage(id.toString());
    setCurrentRow(result)
    setIsLoading(false)
  }

  const tabForField = (field: string): string => {
    if (field === 'status') return 'publish';
    return 'general';
  }

  async function handleSubmit(values: z.infer<typeof formSchema>) {
    setIsLoading(true)
    try {
      const postRequest: PageForm = values
      let request: Page
      if (id) {
        request = await updatePage(id.toString(), postRequest)
        await getItem(id);

        toast.success(tMessage('success.record.updated', {name: entityName.singular}))
      } else {
        request = await createPage(postRequest)
        toast.success(tMessage('success.record.created', {name: entityName.singular}))
        const redirectUrl = FeatureRoutes.EDIT.replace('$id', request.id.toString())
        setId(request.id)
        navigate({ to: redirectUrl })
      }
      await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
    } catch (error) {
      if (error instanceof ApiError) {
        parseAndToastError(error)
      } else {
        toast.error(tMessage('error.general'))
      }
    } finally {
      setIsLoading(false)
    }
  }


  return (
    <Provider>
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
              {
                id ?
                  tPageTitle("form.title_edit", {entity: entityName.singular, id: id.toString()}) :
                  tPageTitle("form.title_create", {entity: entityName.singular})
              }
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("form.subtitle")}
            </p>
          </div>
          <div className='flex gap-2'>
            <Button
              variant='outline'
              className='space-x-1'
              onClick={() => navigate({ to: FeatureRoutes.LIST })}
            >
              <ArrowLeftIcon size={16} />
              <span>{entityName.plural}</span>
              <ListCheckIcon size={18} />
            </Button>
            <Button type="submit" form="my-form" className='btn' disabled={isLoading}>
              <LoaderIcon className={isLoading ? 'animate-spin' : 'hidden'} />
              <SaveIcon />
              {tAction('submit')}
            </Button>
          </div>
        </div>

        <Form {...form}>
          <form
            id="my-form"
            onSubmit={form.handleSubmit(handleSubmit, (errors) => {
              const firstField = Object.keys(errors)[0]
              if (firstField) setActiveTab(tabForField(firstField))
              toast.error(tMessage('error.validation'))
            })}
            className={isLoading ? 'hidden' : 'space-y-8'}
          >

            <Tabs value={activeTab} onValueChange={setActiveTab}>
              <TabsList>
                <TabsTrigger value="general">{tLabel('general')}</TabsTrigger>
                <TabsTrigger value="images">{tLabel('images')}</TabsTrigger>
                <TabsTrigger value="publish">{tLabel('publish')}</TabsTrigger>
              </TabsList>
              <TabsContent value="general" className="pt-5 pb-5">

                {languagesIsLoading && (
                  <div className='py-8 text-center text-sm text-muted-foreground'>
                    {tMessage('info.loading')}
                  </div>
                )}

                {!languagesIsLoading && languagesSafe.length > 0 && (
                  <Tabs defaultValue={languagesSafe[0]?.code} className='w-full'>
                    <TabsList className='w-full'>
                      {languagesSafe.map((language) => (
                        <TabsTrigger key={language.code} value={language.code}>
                          {language.name}
                        </TabsTrigger>
                      ))}
                    </TabsList>

                    {languagesSafe.map((language, index) => (
                      <TabsContent key={language.code} value={language.code} className='space-y-4'>
                        {/* Title Name Field */}
                        <FormField
                          control={form.control}
                          name={`translations.${index}.title`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`title-${language.code}`}>{tLabel('title')}</FormLabel>
                              <FormControl>
                                <Input
                                  id={`title-${language.code}`}
                                  placeholder={tPlaceHolder('input')}
                                  {...field}
                                  value={field.value ?? ''}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        {/* Slug Name Field */}
                        <FormField
                          control={form.control}
                          name={`translations.${index}.slug`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`slug-${language.code}`}>{tLabel('slug')}</FormLabel>
                              <FormControl>
                                <Input
                                  id={`slug-${language.code}`}
                                  placeholder={tPlaceHolder('input')}
                                  {...field}
                                  value={field.value ?? ''}
                                />
                              </FormControl>
                              <FormMessage />
                              <FormDescription>
                                {import.meta.env.VITE_STORE_FRONT_URL+'/page/'+language.code+'/'+(field.value ?? '')}
                              </FormDescription>
                            </FormItem>
                          )}
                        />

                        {/* Description Field */}
                        <FormField
                          control={form.control}
                          name={`translations.${index}.description`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`description-${language.code}`}>{tLabel('description')}</FormLabel>
                              <FormControl>
                                <Textarea
                                  id={`description-${language.code}`}
                                  placeholder={tPlaceHolder('textarea')}
                                  {...field}
                                  value={field.value ?? ''}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        {/* Content Field */}
                        <FormField
                          control={form.control}
                          name={`translations.${index}.content`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`content-${language.code}`}>{tLabel('content')}</FormLabel>
                              <FormControl>
                                <TiptapEditor
                                  initialContent={field.value}
                                  onUpdate={({ html }) => field.onChange(html)}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </TabsContent>
                    ))}
                  </Tabs>
                )}

              </TabsContent>
              <TabsContent value="images" className="pt-5 pb-5">
                <div>
                  {id && <ImageUploader createEndpoint={uploadEndpoints.a} listEndpoint={uploadEndpoints.a} updateEndpoint={uploadEndpoints.b} deleteEndpoint={uploadEndpoints.b} />}
                </div>
              </TabsContent>
              <TabsContent value="publish" className="pt-5 pb-5">

                {/* Status Name Field */}
                <FormField
                  control={form.control}
                  name='status'
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{tLabel('status')}</FormLabel>
                      <Select
                        onValueChange={field.onChange}
                        value={field.value}
                        defaultValue={field.value}
                      >
                        <FormControl className='w-full'>
                          <SelectTrigger>
                            <SelectValue placeholder={tPlaceHolder('select')} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {statuses.map((item: string) => (
                            <SelectItem key={item} value={item}>
                              {tStatus(`status.${item}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </TabsContent>
            </Tabs>

          </form>
        </Form>
      </Main>

    </Provider>
  )
}
