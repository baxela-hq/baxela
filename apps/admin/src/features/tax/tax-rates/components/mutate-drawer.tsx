import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Locales } from '../data/routes'
import {
  formSchema,
  defaultValues,
  type TaxRateForm,
  type TaxRate,
  buildEditValues,
} from '../data/schema'
import { useCountries } from '@/features/core/countries/hooks/use-countries'
import { useTaxClassOptions } from '@/features/tax/tax-classes/hooks/use-tax-classes'
import { useSaveTaxRate } from '../hooks/use-tax-rate-mutations'


type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: TaxRate
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText } = useAppTranslation(Locales.TAX_RATE)

  const entityName = {
    singular: tLabel("tax_rate"),
    plural: tLabel("tax_rates")
  };

  const { data: countries, isLoading: countriesIsLoading } = useCountries()
  const countriesSafe = countries ?? []
  const taxClasses = useTaxClassOptions()

  const form = useForm<TaxRateForm>({
    resolver: zodResolver(formSchema),
    defaultValues: { ...defaultValues },
  })

  useEffect(() => {
    form.reset(buildEditValues(currentRow))
  }, [currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const saveTaxRate = useSaveTaxRate()

  const onSubmit = (data: TaxRateForm) => {
    saveTaxRate.mutate(
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
            id='tax-rates-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
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
              name='country_code'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('country_code')}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger className='w-full'>
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {countriesIsLoading && (
                        <p className='py-4 text-center text-sm text-muted-foreground'>
                          ...
                        </p>
                      )}
                      {!countriesIsLoading && countriesSafe.map((country) => (
                        <SelectItem key={country.code} value={country.code}>
                          {country.emoji} {country.name} ({country.code})
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
              name='tax_class_id'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('tax_class')}</FormLabel>
                  <Select
                    onValueChange={(value) => field.onChange(Number(value))}
                    value={field.value ? String(field.value) : ''}
                  >
                    <FormControl>
                      <SelectTrigger className='w-full'>
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {taxClasses.map((taxClass) => (
                        <SelectItem
                          key={taxClass.id}
                          value={String(taxClass.id)}
                        >
                          {taxClass.name}
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
              name='rate'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('rate')}</FormLabel>
                  <FormControl>
                    <Input
                      type='text'
                      inputMode='decimal'
                      {...field}
                      placeholder={tPlaceHolder('rate')}
                    />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('rate')}
                  </FormDescription>
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
          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='tax-rates-form' type='submit'>
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
