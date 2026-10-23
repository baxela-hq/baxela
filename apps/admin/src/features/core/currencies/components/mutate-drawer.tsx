import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Switch } from '@/components/ui/switch'
import { Locales } from '../data/routes'
import {
  buildEditValues,
  formSchema,
  type CurrencyForm,
  type CurrencyRow,
} from '../data/schema'
import { useUpdateCurrency } from '../hooks/use-currency-mutations'

type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: CurrencyRow
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(
    Locales.SHARED_COMMON
  )
  const { tLabel, tHelpText } = useAppTranslation(Locales.CURRENCY)

  const entityName = { singular: tLabel('currency') }
  // The base currency defines the rate scale — its rate is pinned to 1
  // server-side, so the field stays read-only here.
  const isBase = currentRow.is_default

  const form = useForm<CurrencyForm>({
    resolver: zodResolver(formSchema),
    defaultValues: buildEditValues(currentRow),
  })

  useEffect(() => {
    form.reset(buildEditValues(currentRow))
  }, [currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const updateCurrency = useUpdateCurrency()

  const onSubmit = (data: CurrencyForm) => {
    updateCurrency.mutate(
      { id: currentRow.id, data },
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
        form.reset(buildEditValues(currentRow))
      }}
    >
      <SheetContent className='flex flex-col'>
        <SheetHeader className='text-start'>
          <SheetTitle>
            {tPageTitle('form.title_edit', {
              entity: entityName.singular,
              id: currentRow.id?.toString(),
            })}
          </SheetTitle>
          <SheetDescription>{tPageTitle('form.subtitle')}</SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <form
            id='currencies-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormItem>
              <FormLabel>{tLabel('code')}</FormLabel>
              <FormControl>
                <Input value={currentRow.code} disabled />
              </FormControl>
              <FormDescription>{tHelpText('code')}</FormDescription>
            </FormItem>

            <FormField
              control={form.control}
              name='name'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('name')}</FormLabel>
                  <FormControl>
                    <Input {...field} placeholder={tPlaceHolder('input')} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='native_name'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('native-name')}</FormLabel>
                  <FormControl>
                    <Input {...field} placeholder={tPlaceHolder('input')} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='symbol'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('symbol')}</FormLabel>
                  <FormControl>
                    <Input {...field} placeholder={tPlaceHolder('input')} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='decimal_places'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('decimal-places')}</FormLabel>
                  <Select
                    value={String(field.value)}
                    onValueChange={(value) => field.onChange(Number(value))}
                  >
                    <FormControl>
                      <SelectTrigger className='w-full'>
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {Array.from({ length: 9 }, (_, digits) => (
                        <SelectItem key={digits} value={String(digits)}>
                          {digits}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    {tHelpText('decimal-places')}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='exchange_rate'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('exchange-rate')}</FormLabel>
                  <FormControl>
                    <Input
                      {...field}
                      disabled={isBase}
                      placeholder='0.92000000'
                      className='font-mono'
                    />
                  </FormControl>
                  <FormDescription>
                    {isBase
                      ? tHelpText('exchange-rate-base')
                      : tHelpText('exchange-rate')}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='is_symbol_right'
              render={({ field }) => (
                <FormItem className='flex flex-row items-center justify-between rounded-lg border p-3'>
                  <div className='space-y-0.5'>
                    <FormLabel>{tLabel('is-symbol-right')}</FormLabel>
                    <FormDescription>
                      {tHelpText('is-symbol-right')}
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
          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='currencies-form' type='submit'>
            {tAction('save')}
          </Button>
          <SheetClose asChild>
            <Button variant='outline'>{tAction('close')}</Button>
          </SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
