import { useEffect } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { getDefaultCurrency } from '@/shared/lib/locale';
import { Locales } from '../data/routes'
import {
  formSchema,
  defaultValues,
  type CouponForm,
  type Coupon,
  buildEditValues,
} from '../data/schema'
import { useSaveCoupon } from '../hooks/use-coupon-mutations'
import { UtcDatePicker } from './utc-date-picker.tsx'

type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: Coupon
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText, tStatus, tPlaceHolder: tCouponPlaceHolder } = useAppTranslation(Locales.COUPON)

  const entityName = {
    singular: tLabel("coupon"),
    plural: tLabel("coupons")
  };

  const currency = getDefaultCurrency()

  const form = useForm<CouponForm>({
    resolver: zodResolver(formSchema),
    defaultValues: { ...defaultValues },
  })

  useEffect(() => {
    form.reset(buildEditValues(currentRow))
  }, [currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const type = useWatch({ control: form.control, name: 'type' })
  const isPercent = type === 'percent'

  const saveCoupon = useSaveCoupon()

  const onSubmit = (data: CouponForm) => {
    saveCoupon.mutate(
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
            id='coupons-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormField
              control={form.control}
              name='code'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('code')}</FormLabel>
                  <FormControl>
                    <Input
                      {...field}
                      placeholder={tCouponPlaceHolder('code')}
                      className='font-mono uppercase'
                    />
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
              name='name'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('name')}</FormLabel>
                  <FormControl>
                    <Input
                      value={field.value ?? ''}
                      onChange={field.onChange}
                      placeholder={tCouponPlaceHolder('name')}
                    />
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
              name='type'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('type')}</FormLabel>
                  <Select
                    onValueChange={field.onChange}
                    value={field.value}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value='percent'>{tStatus('percent')}</SelectItem>
                      <SelectItem value='fixed'>{tStatus('fixed')}</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('type')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='value'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('value')}</FormLabel>
                  <FormControl>
                    <InputGroup>
                      <InputGroupInput
                        type="number"
                        min="0"
                        step="0.01"
                        {...field}
                        placeholder={tPlaceHolder('input')}
                      />
                      <InputGroupAddon align='inline-end'>
                        {isPercent ? '%' : (currency?.symbol ?? '$')}
                      </InputGroupAddon>
                    </InputGroup>
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText(isPercent ? 'value_percent' : 'value_fixed')}
                  </FormDescription>
                </FormItem>
              )}
            />

            {isPercent && (
              <FormField
                control={form.control}
                name='max_discount_amount'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tLabel('max_discount_amount')}</FormLabel>
                    <FormControl>
                      <InputGroup>
                        <InputGroupInput
                          type="number"
                          min="0"
                          step="0.01"
                          value={field.value ?? ''}
                          onChange={field.onChange}
                          placeholder={tPlaceHolder('input')}
                        />
                        <InputGroupAddon align={currency?.is_symbol_right ? 'inline-end' : 'inline-start'}>
                          {currency?.symbol ?? '$'}
                        </InputGroupAddon>
                      </InputGroup>
                    </FormControl>
                    <FormMessage />
                    <FormDescription>
                      {tHelpText('max_discount_amount')}
                    </FormDescription>
                  </FormItem>
                )}
              />
            )}

            <FormField
              control={form.control}
              name='min_order_amount'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('min_order_amount')}</FormLabel>
                  <FormControl>
                    <InputGroup>
                      <InputGroupInput
                        type="number"
                        min="0"
                        step="0.01"
                        value={field.value ?? ''}
                        onChange={field.onChange}
                        placeholder={tPlaceHolder('input')}
                      />
                      <InputGroupAddon align={currency?.is_symbol_right ? 'inline-end' : 'inline-start'}>
                        {currency?.symbol ?? '$'}
                      </InputGroupAddon>
                    </InputGroup>
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('min_order_amount')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <div className='grid grid-cols-1 gap-4 sm:grid-cols-2'>
              <FormField
                control={form.control}
                name='usage_limit'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tLabel('usage_limit')}</FormLabel>
                    <FormControl>
                      <Input
                        type='number'
                        min='1'
                        step='1'
                        value={field.value ?? ''}
                        onChange={(e) =>
                          field.onChange(e.target.value === '' ? null : Number(e.target.value))
                        }
                        placeholder={tCouponPlaceHolder('unlimited')}
                      />
                    </FormControl>
                    <FormMessage />
                    <FormDescription>
                      {tHelpText('usage_limit')}
                    </FormDescription>
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name='per_user_limit'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tLabel('per_user_limit')}</FormLabel>
                    <FormControl>
                      <Input
                        type='number'
                        min='1'
                        step='1'
                        value={field.value ?? ''}
                        onChange={(e) =>
                          field.onChange(e.target.value === '' ? null : Number(e.target.value))
                        }
                        placeholder={tCouponPlaceHolder('unlimited')}
                      />
                    </FormControl>
                    <FormMessage />
                    <FormDescription>
                      {tHelpText('per_user_limit')}
                    </FormDescription>
                  </FormItem>
                )}
              />
            </div>

            <div className='grid grid-cols-1 gap-4 sm:grid-cols-2'>
              <FormField
                control={form.control}
                name='starts_at'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tLabel('starts_at')}</FormLabel>
                    <FormControl>
                      <UtcDatePicker
                        selected={field.value ?? undefined}
                        onSelect={field.onChange}
                        placeholder={tCouponPlaceHolder('date')}
                      />
                    </FormControl>
                    <FormMessage />
                    <FormDescription>
                      {tHelpText('starts_at')}
                    </FormDescription>
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name='ends_at'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tLabel('ends_at')}</FormLabel>
                    <FormControl>
                      <UtcDatePicker
                        selected={field.value ?? undefined}
                        onSelect={field.onChange}
                        placeholder={tCouponPlaceHolder('date')}
                      />
                    </FormControl>
                    <FormMessage />
                    <FormDescription>
                      {tHelpText('ends_at')}
                    </FormDescription>
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name='is_active'
              render={({ field }) => (
                <FormItem className='flex flex-row items-center justify-between rounded-lg border p-3 shadow-sm'>
                  <div className='space-y-0.5'>
                    <FormLabel>{tLabel('is_active')}</FormLabel>
                    <FormDescription>
                      {tHelpText('is_active')}
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
          <Button form='coupons-form' type='submit'>
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
