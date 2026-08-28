import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { InfoIcon } from 'lucide-react'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage, } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, } from '@/components/ui/select.tsx'
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, } from '@/components/ui/sheet'
import { Textarea } from '@/components/ui/textarea'
import { updateOrder } from '../api/orders.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { formSchema, type OrderForm, type Order, defaultValues, statuses, } from '../data/schema'

type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: Order
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const queryClient = useQueryClient()
  const { tAction, tMessage, tPageTitle, tPlaceHolder, } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText, tTooltip, tStatus } = useAppTranslation(Locales.ORDER)

  const entityName = {
    singular: tLabel('order'),
    plural: tLabel('orders'),
  }

  const form = useForm<OrderForm>({
    resolver: zodResolver(formSchema),
    defaultValues: currentRow ?? defaultValues,
  })

  const onSubmit = async (data: OrderForm) => {
    if (!currentRow) return;

    try {
      await updateOrder(currentRow.id.toString(), data)

      toast.success(
        tMessage('success.record.updated', { name: entityName.singular })
      )
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
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
            {isUpdate
              ? tPageTitle('form.title_edit', {
                  entity: entityName.singular,
                  id: currentRow?.id?.toString(),
                })
              : tPageTitle('form.title_create', {
                  entity: entityName.singular,
                })}
          </SheetTitle>
          <SheetDescription>{tPageTitle('form.subtitle')}</SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <form
            id='orders-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
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
                  <FormDescription>
                    {tTooltip(`status.${field.value}`)}
                  </FormDescription>
                  <FormDescription>
                    <FormDescription><InfoIcon size="16" className="inline-block" /> <b>{tHelpText(`status`)}</b></FormDescription>
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='note'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('note')}</FormLabel>
                  <FormControl>
                    <Textarea
                      {...field}
                      placeholder={tPlaceHolder('textarea')}
                    />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>{tHelpText(`note`)}</FormDescription>
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
                    <Textarea
                      {...field}
                      placeholder={field.value?.length.toString()}
                      className={!field.value ? 'bg-neutral-100 cursor-not-allowed min-h-10' : 'bg-neutral-100 cursor-not-allowed'}
                      readOnly={true}
                    />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>{tHelpText(`description`)}</FormDescription>
                </FormItem>
              )}
            />

          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <SheetClose asChild>
            <Button variant='outline'>{tAction('close')}</Button>
          </SheetClose>
          <Button form='orders-form' type='submit'>
            {tAction(isUpdate ? 'save' : 'submit')}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
