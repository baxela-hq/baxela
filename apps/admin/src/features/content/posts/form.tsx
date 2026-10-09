import { useEffect, useState } from 'react';
import { type z } from 'zod';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate } from '@tanstack/react-router';
import { useLanguages } from '@/features/core/languages/hooks/use-languages'
import { ListCheckIcon, LoaderIcon, SaveIcon, ArrowLeftIcon } from 'lucide-react';
import { toast } from 'sonner';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Checkbox } from '@/components/ui/checkbox';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage, FormDescription } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea.tsx';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { Search } from '@/components/search'
import { TiptapEditor } from '@/components/tiptap/tiptap-editor'
import { SerpPreview } from '@/components/serp-preview'
import { FeatureRoutes, Locales } from './data/routes';
import { fetchOnePost } from './api/posts.api.ts';
import { useSavePost } from './hooks/use-post-mutations';
import { usePostCategoryTree } from '@/features/content/post-categories/hooks/use-post-categories'
import { PostProductPicker } from './components/product-picker';
import { Provider } from './components/provider.tsx';
import { formSchema, statuses, buildDefaultValues, buildEditValues, type Post } from './data/schema';
import { UtcDatePicker } from '@/features/discount/coupons/components/utc-date-picker';
import { HeaderActions } from '@/components/layout/header-actions'


export function PostForm() {
  const [isLoading, setIsLoading] = useState(false)
  const [id, setId] = useState<number | null>(null)
  const navigate = useNavigate()
  const [currentRow, setCurrentRow] = useState<Post | null>(null)
  const [activeTab, setActiveTab] = useState('general')
  const { tAction,tPageTitle, tPlaceHolder, tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tStatus, tHelpText } = useAppTranslation(Locales.POST)

  const entityName = {
    singular: tLabel("post"),
    plural: tLabel("posts")
  };

  const { data: languages, isLoading: languagesIsLoading } = useLanguages();

  const languagesSafe = languages ?? []

  const savePost = useSavePost()

  const busy = isLoading || savePost.isPending

  const categoryTree = usePostCategoryTree()

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  });

  useEffect(() => {
    if (languagesSafe.length > 0) {
      form.reset(buildEditValues(languagesSafe, currentRow ?? undefined))
    }
  }, [languages, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  async function getItem(id: number) {
    setIsLoading(true)
    const result = await fetchOnePost(id.toString());
    setCurrentRow(result)
    setIsLoading(false)
  }

  useEffect(() => {
    const currentPath = window.location.pathname
    const match = currentPath.match(/^\/content\/posts\/(\d+)\/edit$/);

    if (match && match[1]) {
      const intId = Number(match[1])
      setId(intId)
      const fetchData = async () => {
        getItem(intId)
      }
      fetchData()
    }
  }, [])

  const tabForField = (field: string): string => {
    if (field === 'seo' || field.startsWith('seo')) return 'seo';
    if (field === 'status' || field === 'published_at') return 'publish';
    if (field === 'categories') return 'categories';
    if (field === 'products') return 'products';
    return 'general';
  }

  const handleSubmit = (values: z.infer<typeof formSchema>) => {
    // the form holds a Date; the API takes an ISO instant or null
    const postRequest = {
      ...values,
      published_at: values.published_at ? values.published_at.toISOString() : null,
    }
    savePost.mutate(
      { id, data: postRequest },
      {
        onSuccess: (request, vars) => {
          if (vars.id) {
            getItem(vars.id)
          } else {
            const redirectUrl = FeatureRoutes.EDIT.replace('$id', request.id.toString())
            setId(request.id)
            navigate({ to: redirectUrl })
          }
        },
      }
    )
  }


  return (
    <Provider>
      <Header fixed>
        <Search />
        <HeaderActions />
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
            <Button type="submit" form="my-form" className='btn' disabled={busy}>
              <LoaderIcon className={busy ? 'animate-spin' : 'hidden'} />
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
            className={busy ? 'hidden' : 'space-y-8'}
          >

            <Tabs value={activeTab} onValueChange={setActiveTab}>
              <TabsList>
                <TabsTrigger value="general">{tLabel('general')}</TabsTrigger>
                <TabsTrigger value="categories">{tLabel('categories')}</TabsTrigger>
                <TabsTrigger value="products">{tLabel('products')}</TabsTrigger>
                <TabsTrigger value="seo">{tLabel('seo')}</TabsTrigger>
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
                    <TabsList>
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
                                {import.meta.env.VITE_STORE_FRONT_URL+'/blog/'+language.code+'/'+(field.value ?? '')}
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
              <TabsContent value="categories" className="pt-5 pb-5">
                {/* Categories Field */}
                <FormField
                  control={form.control}
                  name='categories'
                  render={() => (
                    <FormItem>
                      <FormLabel>{tLabel('categories')}</FormLabel>
                      <div className='max-h-64 space-y-2 overflow-y-auto rounded-md border p-3'>
                        {categoryTree.map((category) => (
                          <FormField
                            key={category.id}
                            control={form.control}
                            name='categories'
                            render={({ field }) => (
                              <FormItem
                                key={category.id}
                                className='flex flex-row items-center gap-2'
                                style={{ paddingInlineStart: `${category.depth * 1.25}rem` }}
                              >
                                <FormControl>
                                  <Checkbox
                                    checked={field.value?.includes(category.id)}
                                    onCheckedChange={(checked) =>
                                      field.onChange(
                                        checked
                                          ? [...(field.value ?? []), category.id]
                                          : (field.value ?? []).filter(
                                              (value) => value !== category.id
                                            )
                                      )
                                    }
                                  />
                                </FormControl>
                                <FormLabel className='text-sm font-normal'>
                                  {category.title}
                                </FormLabel>
                              </FormItem>
                            )}
                          />
                        ))}
                      </div>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </TabsContent>
              <TabsContent value="products" className="pt-5 pb-5">
                <FormField
                  control={form.control}
                  name='products'
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{tLabel('products')}</FormLabel>
                      <FormControl>
                        <PostProductPicker
                          selectedIds={field.value ?? []}
                          onChange={field.onChange}
                          label={tLabel('products')}
                          placeholder={tPlaceHolder('search_products')}
                          noResults={tLabel('no_products_found')}
                        />
                      </FormControl>
                      <FormDescription>
                        {tHelpText('products')}
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </TabsContent>
              <TabsContent value="seo" className="pt-5 pb-5">
                {languagesIsLoading && (
                  <div className='py-8 text-center text-sm text-muted-foreground'>
                    {tMessage('info.loading')}
                  </div>
                )}

                {!languagesIsLoading && languagesSafe.length > 0 && (
                  <Tabs defaultValue={languagesSafe[0]?.code} className='w-full'>
                    <TabsList>
                      {languagesSafe.map((language) => (
                        <TabsTrigger key={language.code} value={language.code}>
                          {language.name}
                        </TabsTrigger>
                      ))}
                    </TabsList>

                    {languagesSafe.map((language, index) => (
                      <TabsContent key={language.code} value={language.code} className='space-y-4'>
                        <div className='space-y-2'>
                          <p className='text-sm font-medium text-muted-foreground'>
                            {tLabel('serp_preview')}
                          </p>
                          <SerpPreview
                            title={
                              form.watch(`seo.${index}.meta_title`) ||
                              form.watch(`translations.${index}.title`)
                            }
                            description={
                              form.watch(`seo.${index}.meta_description`) ||
                              form.watch(`translations.${index}.description`)
                            }
                            url={`${import.meta.env.VITE_STORE_FRONT_URL}/blog/${language.code}/${form.watch(`translations.${index}.slug`) || ''}`}
                          />
                        </div>

                        <FormField
                          control={form.control}
                          name={`seo.${index}.meta_title`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`meta-title-${language.code}`}>{tLabel('meta_title')}</FormLabel>
                              <FormControl>
                                <Input
                                  id={`meta-title-${language.code}`}
                                  placeholder={tPlaceHolder('input')}
                                  {...field}
                                  value={field.value ?? ''}
                                  maxLength={255}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        <FormField
                          control={form.control}
                          name={`seo.${index}.meta_description`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`meta-description-${language.code}`}>{tLabel('meta_description')}</FormLabel>
                              <FormControl>
                                <Textarea
                                  id={`meta-description-${language.code}`}
                                  placeholder={tPlaceHolder('textarea')}
                                  {...field}
                                  value={field.value ?? ''}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        <FormField
                          control={form.control}
                          name={`seo.${index}.open_graph_title`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`open-graph-title-${language.code}`}>{tLabel('open_graph_title')}</FormLabel>
                              <FormControl>
                                <Input
                                  id={`open-graph-title-${language.code}`}
                                  placeholder={tPlaceHolder('input')}
                                  {...field}
                                  value={field.value ?? ''}
                                  maxLength={255}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        <FormField
                          control={form.control}
                          name={`seo.${index}.open_graph_description`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`open-graph-description-${language.code}`}>{tLabel('open_graph_description')}</FormLabel>
                              <FormControl>
                                <Textarea
                                  id={`open-graph-description-${language.code}`}
                                  placeholder={tPlaceHolder('textarea')}
                                  {...field}
                                  value={field.value ?? ''}
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

                {/* Publish Date Field */}
                <FormField
                  control={form.control}
                  name='published_at'
                  render={({ field }) => (
                    <FormItem className='mt-6'>
                      <FormLabel>{tLabel('publish_date')}</FormLabel>
                      <FormControl>
                        <UtcDatePicker
                          selected={field.value ?? undefined}
                          onSelect={(date) => field.onChange(date ?? null)}
                          placeholder={tLabel('publish_date')}
                        />
                      </FormControl>
                      <FormDescription>
                        {tHelpText('publish_date')}
                      </FormDescription>
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
