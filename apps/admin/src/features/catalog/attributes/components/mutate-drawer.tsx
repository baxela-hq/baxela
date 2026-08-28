import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { parseAndToastError } from '@/shared/lib/utils';
import { getDefaultLanguage } from '@/shared/lib/locale';
import { toast } from 'sonner';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { createAttribute, updateAttribute } from '../api/attributes.api'
import { Locales, FeatureRoutes } from '../data/routes'
import {
  formSchema,
  DATA_TYPES,
  type AttributeForm,
  type Attribute,
  buildDefaultValues,
  buildEditValues,
} from '../data/schema'
import { fetchAttributeGroups } from '../../attribute-groups/api/attribute-groups.api'
import { type AttributeGroup } from '../../attribute-groups/data/schema'
import { fetchLanguages } from '@/features/core/languages/api/languages.api'
import type { Language } from '@/shared/types/locale.types'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { ApiError } from '@/shared/lib/api-error.ts'


type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: Attribute
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const queryClient = useQueryClient()
  const { tAction, tMessage, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText, tStatus } = useAppTranslation(Locales.ATTRIBUTE)

  const entityName = {
    singular: tLabel("attribute"),
    plural: tLabel("attributes")
  };

  const { data: languages, isLoading: languagesIsLoading } = useQuery<Language[]>({
    queryKey: ['languages'],
    queryFn: () => fetchLanguages(),
  })

  const languagesSafe = languages ?? []

  const { data: groupsData } = useQuery<PaginatedResponse<AttributeGroup>>({
    queryKey: ['attribute-groups', 'all'],
    queryFn: () => fetchAttributeGroups({ per_page: 1000 }),
  })

  const groups = groupsData?.data ?? []

  const form = useForm<AttributeForm>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  })

  useEffect(() => {
    if (languagesSafe.length > 0) {
      form.reset(buildEditValues(languagesSafe, currentRow))
    }
  }, [languages, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const onSubmit = async (data: AttributeForm) => {
    try {
      if (isUpdate){
        await updateAttribute(currentRow?.id.toString(), data)
      } else {
        await createAttribute(data)
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
            id='attributes-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormField
              control={form.control}
              name='group_id'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('group')}</FormLabel>
                  <Select
                    onValueChange={(value) => field.onChange(Number(value))}
                    value={field.value ? String(field.value) : undefined}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {groups.map((group) => {
                        const index = getDefaultLanguage(group.translations) ?? 0
                        const title = group.translations[index]?.title ?? String(group.id)
                        return (
                          <SelectItem key={group.id} value={String(group.id)}>
                            {title}
                          </SelectItem>
                        )
                      })}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

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
              name='data_type'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('data_type')}</FormLabel>
                  <Select
                    onValueChange={(value) => field.onChange(value)}
                    value={field.value}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {DATA_TYPES.map((dataType) => (
                        <SelectItem key={dataType} value={dataType}>
                          {tStatus(`data_type.${dataType}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='is_filterable'
              render={({ field }) => (
                <FormItem className='flex flex-row items-center justify-between rounded-lg border p-3'>
                  <div className='space-y-0.5'>
                    <FormLabel>{tLabel('is_filterable')}</FormLabel>
                    <FormDescription>
                      {tHelpText('is_filterable')}
                    </FormDescription>
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
          <Button form='attributes-form' type='submit'>
            {tAction(isUpdate ? 'save' : 'submit')}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
