import { useEffect, useState } from 'react';
import { type z } from 'zod';
import { useFieldArray, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate } from '@tanstack/react-router';
import { useLanguages } from '@/features/core/languages/hooks/use-languages'
import { ListCheckIcon, LoaderIcon, SaveIcon, ArrowLeftIcon, XIcon, ImagePlusIcon, ChevronLeftIcon, ChevronRightIcon, StarIcon, ExternalLinkIcon } from 'lucide-react';
import { toast } from 'sonner';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Checkbox } from '@/components/ui/checkbox';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage, FormDescription } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea.tsx';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { Search } from '@/components/search'
import { TiptapEditor } from '@/components/tiptap/tiptap-editor'
import { SerpPreview } from '@/components/serp-preview'
import { cn } from '@/lib/utils';
import { MediaPickerDialog } from '@/features/media/components/media-picker-dialog';
import { type MediaItem, getMediaUrl } from '@/features/media/data/schema';
import { FeatureRoutes, Locales } from './data/routes';
import { fetchOnePost } from './api/posts.api.ts';
import { useSavePost } from './hooks/use-post-mutations';
import { usePostCategoryTree } from '@/features/content/post-categories/hooks/use-post-categories'
import { Provider } from './components/provider.tsx';
import { formSchema, statuses, IMAGE_COLLECTION, buildDefaultValues, buildEditValues, type Post } from './data/schema';
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

  const [mediaPickerOpen, setMediaPickerOpen] = useState(false)
  const [previewImage, setPreviewImage] = useState<string | null>(null)

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  });

  const {
    fields: imageFields,
    append: appendImages,
    remove: removeImage,
    move: moveImage,
  } = useFieldArray({
    control: form.control,
    name: 'images',
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
    if (field === 'images' || field.startsWith('images')) return 'images';
    if (field === 'seo' || field.startsWith('seo')) return 'seo';
    if (field === 'status' || field === 'published_at') return 'publish';
    if (field === 'categories') return 'categories';
    return 'general';
  }

  const handleSelectImages = (items: MediaItem[]) => {
    const existingIds = new Set(form.getValues('images').map((image) => image.media_id));
    const nextPosition = form.getValues('images').length;
    const additions = items
      .filter((item) => !existingIds.has(item.id))
      .map((item, index) => ({
        position: nextPosition + index + 1,
        collection: IMAGE_COLLECTION,
        media_id: item.id,
        url: getMediaUrl(item) ?? '',
      }));
    if (additions.length > 0) appendImages(additions);
  };

  const handleMoveImage = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= imageFields.length) return;
    moveImage(index, target);
  };

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
                <TabsTrigger value="images">{tLabel('images')}</TabsTrigger>
                <TabsTrigger value="categories">{tLabel('categories')}</TabsTrigger>
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
              <TabsContent value="images" className="pt-5 pb-5">
                <FormField
                  control={form.control}
                  name="images"
                  render={() => (
                    <FormItem>
                      <FormLabel>{tLabel('images')}</FormLabel>
                      <div className="flex flex-wrap items-start gap-4">
                        <button
                          type="button"
                          onClick={() => setMediaPickerOpen(true)}
                          className="flex h-32 w-32 flex-col items-center justify-center gap-2 rounded-md border border-dashed text-muted-foreground transition-colors hover:border-primary hover:bg-muted/50 hover:text-foreground"
                        >
                          <ImagePlusIcon size={24} />
                          <span className="px-2 text-center text-xs font-medium">
                            {tLabel('add_images')}
                          </span>
                        </button>

                        {imageFields.map((image, index) => (
                          <div key={image.id} className="space-y-1.5">
                            <button
                              type="button"
                              onClick={() => setPreviewImage(form.getValues(`images.${index}.url`))}
                              title={tLabel('image_preview')}
                              aria-label={tLabel('image_preview')}
                              className={cn(
                                'relative h-32 w-32 cursor-zoom-in overflow-hidden rounded-md border',
                                index === 0
                                  ? 'border-2 border-primary dark:border-amber-400'
                                  : 'border-border hover:border-primary/50'
                              )}
                            >
                              <img
                                src={form.getValues(`images.${index}.url`)}
                                alt={(form.getValues(`images.${index}.url`) ?? '').split('/').pop() ?? ''}
                                className="h-full w-full object-cover"
                              />
                              {index === 0 && (
                                <div className='absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-primary/95 py-1 text-primary-foreground'>
                                  <StarIcon size={12} className='fill-current shrink-0' />
                                  <span className='text-xs font-semibold'>
                                    {tLabel('featured_image')}
                                  </span>
                                </div>
                              )}
                            </button>
                            <div className="flex items-center justify-center gap-1">
                              <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-7 w-7"
                                disabled={index === 0}
                                onClick={() => handleMoveImage(index, -1)}
                                title={tLabel('move_left')}
                                aria-label={tLabel('move_left')}
                              >
                                <ChevronLeftIcon size={14} className="rtl:rotate-180" />
                              </Button>
                              <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-7 w-7 text-destructive hover:text-destructive"
                                onClick={() => removeImage(index)}
                                title={tAction('remove')}
                                aria-label={tAction('remove')}
                              >
                                <XIcon size={14} />
                              </Button>
                              <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-7 w-7"
                                disabled={index === imageFields.length - 1}
                                onClick={() => handleMoveImage(index, 1)}
                                title={tLabel('move_right')}
                                aria-label={tLabel('move_right')}
                              >
                                <ChevronRightIcon size={14} className="rtl:rotate-180" />
                              </Button>
                            </div>
                          </div>
                        ))}
                      </div>
                      <FormDescription>
                        {tHelpText('images')}
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <MediaPickerDialog
                  open={mediaPickerOpen}
                  onOpenChange={setMediaPickerOpen}
                  multiple
                  accept="image/*"
                  onSelect={handleSelectImages}
                />

                <Dialog
                  open={previewImage !== null}
                  onOpenChange={(open) => {
                    if (!open) setPreviewImage(null)
                  }}
                >
                  <DialogContent className="max-w-fit">
                    <DialogHeader className="text-start">
                      <DialogTitle>{tLabel('image_preview')}</DialogTitle>
                    </DialogHeader>
                    <img
                      src={previewImage ?? ''}
                      alt={tLabel('image_preview')}
                      className="max-h-[70vh] w-auto max-w-full object-contain"
                    />
                    <DialogFooter>
                      <Button variant="outline" asChild>
                        <a
                          href={previewImage ?? '#'}
                          target="_blank"
                          rel="noreferrer"
                        >
                          <ExternalLinkIcon size={16} />
                          {tLabel('open_original')}
                        </a>
                      </Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>
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
