import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { ChevronDown } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Checkbox } from '@/components/ui/checkbox.tsx'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { Locales } from '../data/routes'
import { formSchema, type RoleForm, type Role, type Permission, defaultValues } from '../data/schema';
import { useSaveRole } from '../hooks/use-role-mutations'
import { usePermissions } from '../hooks/use-permissions'

type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: Role
}

function groupPermissions(permissions: Permission[], search: string) {
  const query = search.trim().toLowerCase()
  const groups = new Map<string, Permission[]>()

  for (const permission of permissions) {
    if (query && !permission.name.toLowerCase().includes(query)) continue
    const module = permission.name.split('.')[0]
    const bucket = groups.get(module) ?? []
    bucket.push(permission)
    groups.set(module, bucket)
  }

  return Array.from(groups.entries())
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([module, items]) => ({
      module,
      permissions: items.sort((a, b) => a.name.localeCompare(b.name)),
    }))
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const { tAction, tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText, tPlaceHolder } = useAppTranslation(Locales.ROLE)
  const { data: permissions, isLoading: permissionsIsLoading } = usePermissions()
  const [permissionSearch, setPermissionSearch] = useState('')

  const entityName = {
    singular: tLabel('role'),
    plural: tLabel('roles')
  };

  const form = useForm<RoleForm>({
    resolver: zodResolver(formSchema),
    defaultValues: currentRow
      ? {
          name: currentRow.name,
          permission_ids: currentRow.permissions.map((permission) => permission.id),
        }
      : defaultValues,
  })

  const saveRole = useSaveRole()
  const groups = useMemo(
    () => groupPermissions(permissions ?? [], permissionSearch),
    [permissions, permissionSearch]
  )

  const onSubmit = (data: RoleForm) => {
    saveRole.mutate(
      { id: currentRow?.id?.toString(), data },
      {
        onSuccess: () => {
          onOpenChange(false)
          form.reset()
        },
      }
    )
  }

  const togglePermission = (
    selected: number[],
    permissionId: number,
    checked: boolean | 'indeterminate'
  ) => {
    return checked
      ? [...selected, permissionId]
      : selected.filter((value) => value !== permissionId)
  }

  const renderPermissionPicker = (field: { value: number[]; onChange: (value: number[]) => void }) => (
    <div className='space-y-2'>
      <Input
        value={permissionSearch}
        onChange={(event) => setPermissionSearch(event.target.value)}
        placeholder={tPlaceHolder('search_permissions')}
        className='mb-1'
      />
      <div className='max-h-72 space-y-1 overflow-y-auto rounded-md border p-2'>
        {permissionsIsLoading && (
          <p className='py-4 text-center text-sm text-muted-foreground'>...</p>
        )}
        {!permissionsIsLoading && groups.length === 0 && (
          <p className='py-4 text-center text-sm text-muted-foreground'>
            {tLabel('no_permission_results')}
          </p>
        )}
        {groups.map((group) => {
          const groupIds = group.permissions.map((permission) => permission.id)
          const selectedInGroup = field.value.filter((id) =>
            groupIds.includes(id)
          )
          const allSelected = selectedInGroup.length === groupIds.length
          const someSelected =
            selectedInGroup.length > 0 && !allSelected

          return (
            <Collapsible key={group.module} defaultOpen className='group/collapsible'>
              <div className='flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted/50'>
                <Checkbox
                  checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                  onCheckedChange={(checked) => {
                    field.onChange(
                      checked
                        ? Array.from(new Set([...field.value, ...groupIds]))
                        : field.value.filter(
                            (id) => !groupIds.includes(id)
                          )
                    )
                  }}
                  aria-label={tLabel('select_all')}
                />
                <CollapsibleTrigger className='flex flex-1 items-center justify-between gap-2 text-sm font-medium'>
                  <span>{group.module}</span>
                  <span className='text-xs text-muted-foreground'>
                    {selectedInGroup.length}/{groupIds.length}
                  </span>
                  <ChevronDown
                    className='size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]/collapsible:rotate-180'
                  />
                </CollapsibleTrigger>
              </div>
              <CollapsibleContent>
                <div className='space-y-1 border-s-2 border-muted ps-4 pb-1 pt-1'>
                  {group.permissions.map((permission) => (
                    <label
                      key={permission.id}
                      className='flex flex-row items-center gap-2'
                    >
                      <Checkbox
                        checked={field.value.includes(permission.id)}
                        onCheckedChange={(checked) =>
                          field.onChange(
                            togglePermission(
                              field.value,
                              permission.id,
                              checked
                            )
                          )
                        }
                      />
                      <span className='font-mono text-xs font-normal'>
                        {permission.name}
                      </span>
                    </label>
                  ))}
                </div>
              </CollapsibleContent>
            </Collapsible>
          )
        })}
      </div>
    </div>
  )

  return (
    <Sheet
      open={open}
      onOpenChange={(v) => {
        onOpenChange(v)
        form.reset()
        setPermissionSearch('')
      }}
    >
      <SheetContent className='flex flex-col'>
        <SheetHeader className='text-start'>
          <SheetTitle>
            {
              isUpdate ?
                tPageTitle('form.title_edit', {entity: entityName.singular, id: currentRow?.id?.toString()}) :
                tPageTitle('form.title_create', {entity: entityName.singular})
            }
          </SheetTitle>
          <SheetDescription>
            {tPageTitle('form.subtitle')}
          </SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <form
            id='roles-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >

            <FormField
              control={form.control}
              name='name'
              render={({ field }) => (
                <FormItem className='grid gap-2'>
                  <FormLabel htmlFor='name'>{tLabel('name')}</FormLabel>
                  <FormControl>
                    <Input id='name' {...field} />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('name')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='permission_ids'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('permissions')}</FormLabel>
                  {renderPermissionPicker(field)}
                  <FormDescription>
                    {tHelpText('permissions')}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='roles-form' type='submit'>
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
