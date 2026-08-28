import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { parseAndToastError } from '@/shared/lib/utils';
import { buildHierarchy } from '@/shared/lib/tree';
import { getDefaultLanguage } from '@/shared/lib/locale';
import { toast } from 'sonner';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea.tsx';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { createCategory, fetchCategories, updateCategory } from '../api/categories.api'
import { Locales, FeatureRoutes } from '../data/routes'
import {
  formSchema,
  type CategoryForm,
  type Category,
  buildDefaultValues,
  buildEditValues,
} from '../data/schema'
import { fetchLanguages } from '@/features/core/languages/api/languages.api'
import type { Language } from '@/shared/types/locale.types'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { ApiError } from '@/shared/lib/api-error.ts'


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
  const queryClient = useQueryClient()
  const { tAction, tMessage, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText } = useAppTranslation(Locales.CATEGORY)

  const entityName = {
    singular: tLabel("category"),
    plural: tLabel("categories")
  };

  const { data: languages, isLoading: languagesIsLoading } = useQuery<Language[]>({
    queryKey: ['languages'],
    queryFn: () => fetchLanguages(),
  })

  const languagesSafe = languages ?? []

  const [categoryTree, setCategoryTree] = useState<(Category & { title: string; depth: number })[]>([]);

  useEffect(() => {
    fetchCategories({ per_page: 1000 })
      .then((res: PaginatedResponse<Category>) => {
        const tree = buildHierarchy<Category>(res.data, (cat) => {
          const index = getDefaultLanguage(cat.translations);
          return cat.translations[index ?? 0]?.title ?? '';
        });

        if (currentRow) {
          const filtered: (Category & { title: string; depth: number })[] = [];
          let excluding = false;
          let excludeDepth = 0;
          for (const item of tree) {
            if (item.id === currentRow.id) {
              excluding = true;
              excludeDepth = item.depth;
              continue;
            }
            if (excluding) {
              if (item.depth > excludeDepth) continue;
              excluding = false;
            }
            filtered.push(item);
          }
          setCategoryTree(filtered);
        } else {
          setCategoryTree(tree);
        }
      })
      .catch((error) => parseAndToastError(error));
  }, [currentRow])

  const form = useForm<CategoryForm>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  })

  useEffect(() => {
    if (languagesSafe.length > 0) {
      form.reset(buildEditValues(languagesSafe, currentRow))
    }
  }, [languages, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const onSubmit = async (data: CategoryForm) => {
    try {
      if (isUpdate){
        await updateCategory(currentRow?.id.toString(), data)
      } else {
        await createCategory(data)
      }
      toast.success(tMessage(`success.record.${isUpdate ? 'updated' : 'created'}`, {name: entityName.singular}))
      await queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
      onOpenChange(false)
      form.reset()
    } catch (err: unknown) {
      if (err instanceof ApiError) {
        parseAndToastError(err)
      } else {
        toast.error(tMessage('error.general'))
      }
    }
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
        <SheetFooter className='gap-2'>
          <SheetClose asChild>
            <Button variant='outline'>{tAction('close')}</Button>
          </SheetClose>
          <Button form='categories-form' type='submit'>
            {tAction(isUpdate ? 'save' : 'submit')}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
