import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { ImagePlusIcon, XIcon } from 'lucide-react';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea.tsx';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MediaPickerDialog } from '@/features/media/components/media-picker-dialog';
import { type MediaItem, getMediaUrl } from '@/features/media/data/schema';
import { Locales } from '../data/routes'
import {
  formSchema,
  type CategoryForm,
  type Category,
  buildDefaultValues,
  buildEditValues,
} from '../data/schema'
import { useLanguages } from '@/features/core/languages/hooks/use-languages'
import { useCategoryTree } from '../hooks/use-categories'
import { useSaveCategory } from '../hooks/use-category-mutations'


type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: Category
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText } = useAppTranslation(Locales.CATEGORY)

  const entityName = {
    singular: tLabel("category"),
    plural: tLabel("categories")
  };

  const { data: languages, isLoading: languagesIsLoading } = useLanguages()

  const languagesSafe = languages ?? []

  const categoryTree = useCategoryTree(currentRow?.id)

  const form = useForm<CategoryForm>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  })

  useEffect(() => {
    if (languagesSafe.length > 0) {
      form.reset(buildEditValues(languagesSafe, currentRow))
    }
  }, [languages, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const saveCategory = useSaveCategory()

  const [mediaPickerOpen, setMediaPickerOpen] = useState(false)

  const handleSelectImage = (item: MediaItem) => {
    form.setValue('image_media_id', item.id, { shouldDirty: true })
    form.setValue('image_url', getMediaUrl(item), { shouldDirty: true })
  }

  const handleRemoveImage = () => {
    form.setValue('image_media_id', null, { shouldDirty: true })
    form.setValue('image_url', null, { shouldDirty: true })
  }

  const onSubmit = (data: CategoryForm) => {
    saveCategory.mutate(
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
            id='categories-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormField
              control={form.control}
              name='parent_id'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('parent_id')}</FormLabel>
                  <Select
                    onValueChange={(value) => field.onChange(value === 'none' ? null : Number(value))}
                    value={field.value ? String(field.value) : 'none'}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="none">{tLabel('none')}</SelectItem>
                      {categoryTree.map((cat) => (
                        <SelectItem
                          key={cat.id}
                          value={String(cat.id)}
                          style={{ paddingInlineStart: `${cat.depth * 1.25 + 0.5}rem` }}
                        >
                          {cat.title}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('parent_id')}
                  </FormDescription>
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

            <FormField
              control={form.control}
              name='image_url'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('image')}</FormLabel>
                  <div className='flex flex-wrap items-start gap-4'>
                    <button
                      type='button'
                      onClick={() => setMediaPickerOpen(true)}
                      className='flex h-32 w-32 flex-col items-center justify-center gap-2 rounded-md border border-dashed text-muted-foreground transition-colors hover:border-primary hover:bg-muted/50 hover:text-foreground'
                    >
                      <ImagePlusIcon size={24} />
                      <span className='px-2 text-center text-xs font-medium'>
                        {tLabel('add_image')}
                      </span>
                    </button>
                    {field.value && (
                      <div className='space-y-1.5'>
                        <div className='h-32 w-32 overflow-hidden rounded-md border'>
                          <img
                            src={field.value}
                            alt={field.value.split('/').pop() ?? ''}
                            className='h-full w-full object-cover'
                          />
                        </div>
                        <div className='flex items-center justify-center'>
                          <Button
                            type='button'
                            variant='outline'
                            size='icon'
                            className='h-7 w-7 text-destructive hover:text-destructive'
                            onClick={handleRemoveImage}
                            title={tAction('remove')}
                            aria-label={tAction('remove')}
                          >
                            <XIcon size={14} />
                          </Button>
                        </div>
                      </div>
                    )}
                  </div>
                  <FormDescription>
                    {tHelpText('image')}
                  </FormDescription>
                  <FormMessage />
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
                <TabsList>
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
                      name={`translations.${index}.title`}
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{tLabel('title')}</FormLabel>
                          <FormControl>
                            <Input {...field} placeholder={tPlaceHolder('input')} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name={`translations.${index}.slug`}
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{tLabel('slug')}</FormLabel>
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
        <MediaPickerDialog
          open={mediaPickerOpen}
          onOpenChange={setMediaPickerOpen}
          accept='image/*'
          onSelect={handleSelectImage}
        />
        <SheetFooter className='gap-2'>
          <Button form='categories-form' type='submit'>
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
