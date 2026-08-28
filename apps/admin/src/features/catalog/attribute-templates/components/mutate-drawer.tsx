import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { parseAndToastError } from '@/shared/lib/utils';
import { getDefaultLanguage } from '@/shared/lib/locale';
import { toast } from 'sonner';
import { ChevronDown, ChevronUp, Trash2 } from 'lucide-react';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { createAttributeTemplate, updateAttributeTemplate, fetchOneAttributeTemplate } from '../api/attribute-templates.api'
import { fetchAttributeGroups } from '../../attribute-groups/api/attribute-groups.api'
import { type AttributeGroup } from '../../attribute-groups/data/schema'
import { Locales, FeatureRoutes } from '../data/routes'
import {
  formSchema,
  defaultValues,
  type AttributeTemplateForm,
  type AttributeTemplate,
  buildEditValues,
} from '../data/schema'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { ApiError } from '@/shared/lib/api-error.ts'


type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: AttributeTemplate
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const queryClient = useQueryClient()
  const { tAction, tMessage, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText } = useAppTranslation(Locales.ATTRIBUTE_TEMPLATE)

  const entityName = {
    singular: tLabel("attribute_template"),
    plural: tLabel("attribute_templates")
  };

  const { data: groupsData } = useQuery<PaginatedResponse<AttributeGroup>>({
    queryKey: ['attribute-groups', 'all'],
    queryFn: () => fetchAttributeGroups({ per_page: 1000 }),
  })

  const groups = groupsData?.data ?? []

  // The list response carries no `groups[]` — fetch the detail (ordered) for edit values.
  const detailId = currentRow?.id
  const { data: detail, isSuccess: detailIsSuccess } = useQuery<AttributeTemplate>({
    queryKey: [FeatureRoutes.CACHE_SINGLE_KEY, detailId],
    queryFn: () => fetchOneAttributeTemplate(detailId!.toString()),
    enabled: isUpdate,
  })

  const form = useForm<AttributeTemplateForm>({
    resolver: zodResolver(formSchema),
    defaultValues: { ...defaultValues },
  })

  useEffect(() => {
    if (isUpdate) {
      if (detailIsSuccess && detail) {
        form.reset(buildEditValues(detail))
      }
    } else {
      form.reset({ ...defaultValues })
    }
  }, [currentRow, detail, detailIsSuccess]) // eslint-disable-line react-hooks/exhaustive-deps

  const groupTitle = (group: AttributeGroup) => {
    const index = getDefaultLanguage(group.translations) ?? 0
    return group.translations[index]?.title ?? String(group.id)
  }

  const groupNames = groups.reduce<Record<number, string>>((acc, group) => {
    acc[group.id] = groupTitle(group)
    return acc
  }, {})

  const onSubmit = async (data: AttributeTemplateForm) => {
    try {
      if (isUpdate){
        await updateAttributeTemplate(currentRow?.id.toString(), data)
      } else {
        await createAttributeTemplate(data)
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
            id='attribute-templates-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormField
              control={form.control}
              name='title'
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
              name='description'
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

            <FormField
              control={form.control}
              name='group_ids'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('groups')}</FormLabel>
                  <div className='max-h-48 space-y-2 overflow-y-auto rounded-md border p-3'>
                    {groups.map((group) => (
                      <label
                        key={group.id}
                        className='flex flex-row items-center gap-2'
                      >
                        <Checkbox
                          checked={field.value.includes(group.id)}
                          onCheckedChange={(checked) => {
                            return checked
                              ? field.onChange([...field.value, group.id])
                              : field.onChange(
                                  field.value.filter(
                                    (value: number) => value !== group.id
                                  )
                                )
                          }}
                        />
                        <span className='text-sm font-normal'>
                          {groupTitle(group)}
                        </span>
                      </label>
                    ))}
                  </div>

                  {field.value.length > 0 && (
                    <div className='space-y-2'>
                      {field.value.map((groupId, index) => (
                        <div
                          key={groupId}
                          className='flex items-center justify-between rounded-md border p-2'
                        >
                          <span className='text-sm'>
                            {index + 1}. {groupNames[groupId] ?? groupId}
                          </span>
                          <div className='flex gap-1'>
                            <Button
                              type='button'
                              variant='ghost'
                              size='icon'
                              className='size-7'
                              disabled={index === 0}
                              aria-label='Move up'
                              onClick={() => {
                                const next = [...field.value]
                                ;[next[index - 1], next[index]] = [next[index], next[index - 1]]
                                field.onChange(next)
                              }}
                            >
                              <ChevronUp size={14} />
                            </Button>
                            <Button
                              type='button'
                              variant='ghost'
                              size='icon'
                              className='size-7'
                              disabled={index === field.value.length - 1}
                              aria-label='Move down'
                              onClick={() => {
                                const next = [...field.value]
                                ;[next[index + 1], next[index]] = [next[index], next[index + 1]]
                                field.onChange(next)
                              }}
                            >
                              <ChevronDown size={14} />
                            </Button>
                            <Button
                              type='button'
                              variant='ghost'
                              size='icon'
                              className='size-7'
                              aria-label='Remove'
                              onClick={() => {
                                field.onChange(
                                  field.value.filter(
                                    (value: number) => value !== groupId
                                  )
                                )
                              }}
                            >
                              <Trash2 size={14} />
                            </Button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  <FormDescription>
                    {tHelpText('groups')}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='attribute-templates-form' type='submit'>
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
