import { useEffect, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Locales } from '../data/routes'
import {
  formSchema,
  type MenuLinkForm,
  type MenuLink,
  buildDefaultValues,
  buildEditValues,
  MENU_LINK_TARGETS,
  NO_PARENT,
} from '../data/schema'
import { useLanguages } from '@/features/core/languages/hooks/use-languages'
import { useSaveMenuLink } from '../hooks/use-menu-link-mutations'
import { useMenuLinksAll } from '../hooks/use-menu-links'
import { buildHierarchy, excludeSubtree } from '@/shared/lib/tree.ts'
import { pickTranslation } from '@/shared/lib/locale.ts'


type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: MenuLink
  menuId: string
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
  menuId,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText } = useAppTranslation(Locales.MENU_LINK)

  const entityName = {
    singular: tLabel("link"),
    plural: tLabel("links")
  };

  const { data: languages, isLoading: languagesIsLoading } = useLanguages()

  const languagesSafe = languages ?? []

  const allLinks = useMenuLinksAll(menuId)

  const parentTree = useMemo(() => {
    const tree = buildHierarchy(allLinks, (link) =>
      pickTranslation(link.translations)?.title || `#${link.id}`
    )
    return excludeSubtree(tree, currentRow?.id)
  }, [allLinks, currentRow])

  const form = useForm<MenuLinkForm>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  })

  useEffect(() => {
    if (languagesSafe.length > 0) {
      form.reset(buildEditValues(languagesSafe, currentRow))
    }
  }, [languages, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const saveMenuLink = useSaveMenuLink()

  const onSubmit = (data: MenuLinkForm) => {
    saveMenuLink.mutate(
      { menuId, id: currentRow?.id?.toString(), data },
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
            id='menu-links-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormField
              control={form.control}
              name='parent_id'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('parent')}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value={NO_PARENT}>
                        {tLabel('no_parent')}
                      </SelectItem>
                      {parentTree.map((node) => (
                        <SelectItem
                          key={node.id}
                          value={node.id.toString()}
                          style={{ paddingInlineStart: `${node.depth * 1.25 + 0.5}rem` }}
                        >
                          {node.title}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('parent')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='url'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('url')}</FormLabel>
                  <FormControl>
                    <Input {...field} placeholder='/products' />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('url')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='target'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('target')}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {MENU_LINK_TARGETS.map((target) => (
                        <SelectItem key={target} value={target}>
                          {tLabel(`target_${target}`)}
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
                      name={`translations.${index}.description`}
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{tLabel('description')}</FormLabel>
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
          <Button form='menu-links-form' type='submit'>
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
